<?php
namespace Icicle\Http\Client;

use Icicle\Dns;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicRequest;
use Icicle\Stream\ReadableStream;

class Client
{
    const DEFAULT_CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    const DEFAULT_TIMEOUT = 15;

    /**
     * @var \Icicle\Http\Client\Requester
     */
    private $requester;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        $options = array_merge($options, ['allow_persistent' => false]);

        $this->requester = new Requester($options);
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
    ) {
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
    public function send(Request $request, array $options = [])
    {
        $request = $request->withHeader('Connection', 'close');

        $uri = $request->getUri();

        /** @var \Icicle\Socket\Socket $socket */
        $socket = (yield Dns\connect($uri->getHost(), $uri->getPort(), $options));

        try {
            if ($uri->getScheme() === 'https') {
                $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
                $cryptoMethod = isset($options['crypto_method'])
                    ? (int) $options['crypto_method']
                    : self::DEFAULT_CRYPTO_METHOD;

                yield $socket->enableCrypto($cryptoMethod, $timeout);
            }

            yield $this->requester->send($socket, $request, $options);
        } finally {
            $socket->close();
        }
    }
}
