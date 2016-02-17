<?php
namespace Icicle\Http\Client;

use Icicle\Dns;
use Icicle\Exception\InvalidArgumentError;
use Icicle\Http\Driver\Driver;
use Icicle\Http\Exception\RedirectException;
use Icicle\Http\Message\{BasicUri, Request, BasicRequest, Response};
use Icicle\Stream\ReadableStream;

class Client
{
    const DEFAULT_CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    const DEFAULT_TIMEOUT = 15;
    const DEFAULT_MAX_REDIRECTS = 10;

    /**
     * @var \Icicle\Http\Client\Requester
     */
    private $requester;

    /**
     * @var bool True to follow 3xx redirects, false to return redirect response.
     */
    private $follow = true;

    /**
     * @var int Max number of redirect responses to follow before failing.
     */
    private $maxRedirects = self::DEFAULT_MAX_REDIRECTS;

    /**
     * @param \Icicle\Http\Driver\Driver $driver
     * @param mixed[] $options
     */
    public function __construct(Driver $driver = null, array $options = [])
    {
        $this->follow = isset($options['follow_redirects']) ? (bool) $options['follow_redirects'] : true;
        $this->maxRedirects = isset($options['max_redirects'])
            ? (int) $options['max_redirects']
            : self::DEFAULT_MAX_REDIRECTS;

        $this->requester = new Requester($driver);
    }

    /**
     * @coroutine
     *
     * @param string $method
     * @param string|\Icicle\Http\Message\Uri $uri
     * @param string[][] $headers
     * @param \Icicle\Stream\ReadableStream|null $body
     * @param mixed[] $options
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function request(
        $method,
        $uri,
        array $headers = [],
        ReadableStream $body = null,
        array $options = []
    ): \Generator {
        return $this->send(new BasicRequest($method, $uri, $headers, $body), $options);
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Request $request
     * @param mixed[] $options
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function send(Request $request, array $options = []): \Generator
    {
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $cryptoMethod = isset($options['crypto_method'])
            ? (int) $options['crypto_method']
            : self::DEFAULT_CRYPTO_METHOD;

        $request = $request->withHeader('Connection', 'close');
        $count = 0;

        try {
            do {
                $uri = $request->getUri();
                $host = $uri->getHost();
                $port = $uri->getPort();

                if ('' === $host || 0 === $port) {
                    throw new InvalidArgumentError('Request URI must have a host and port.');
                }

                /** @var \Icicle\Socket\Socket $socket */
                $socket = yield from Dns\connect($uri->getHost(), $uri->getPort(), $options);

                if ($uri->getScheme() === 'https') {
                    yield from $socket->enableCrypto($cryptoMethod, $timeout);
                }

                /** @var \Icicle\Http\Message\Response $response */
                $response = yield from $this->requester->send($socket, $request, $options);

                if ($this->follow) {
                    switch ($response->getStatusCode()) {
                        case Response::SEE_OTHER:
                            $request = $request->withMethod($request->getMethod() === 'HEAD' ? 'HEAD' : 'GET');
                            // No break.

                        case Response::MOVED_PERMANENTLY:
                        case Response::FOUND:
                        case Response::TEMPORARY_REDIRECT:
                        case Response::PERMANENT_REDIRECT:
                            $socket->close(); // Close original connection.

                            if (++$count > $this->maxRedirects) {
                                throw new RedirectException(
                                    sprintf('Too many redirects encountered (max redirects: %d).', $this->maxRedirects)
                                );
                            }

                            if (!$response->hasHeader('Location')) {
                                throw new RedirectException('No Location header found in redirect response.');
                            }

                            $request = $request->withUri(new BasicUri($response->getHeader('Location')));

                            $response = null; // Let's go around again!
                    }
                }
            } while (null === $response);
        } finally {
            $request->getBody()->close();
        }

        return $response;
    }
}
