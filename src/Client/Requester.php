<?php
namespace Icicle\Http\Client;

use Icicle\Http\Driver\{Driver, Http1Driver};
use Icicle\Http\Message\{Request, BasicRequest};
use Icicle\Socket\Socket;
use Icicle\Stream\ReadableStream;

class Requester
{
    const DEFAULT_TIMEOUT = 15;

    /**
     * @var \Icicle\Http\Driver\Driver
     */
    private $driver;

    /**
     * @param \Icicle\Http\Driver\Driver
     */
    public function __construct(Driver $driver = null)
    {
        $this->driver = $driver ?: new Http1Driver();
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
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
        Socket $socket,
        string $method,
        $uri,
        array $headers = [],
        ReadableStream $body = null,
        array $options = []
    ): \Generator {
        return yield from $this->send($socket, new BasicRequest($method, $uri, $headers, $body), $options);
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Request $request
     * @param mixed[] $options
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function send(Socket $socket, Request $request, array $options = []): \Generator
    {
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;

        $request = yield from $this->driver->buildRequest($request, $timeout, $allowPersistent);

        yield from $this->driver->writeRequest($socket, $request, $timeout);

        return yield from $this->driver->readResponse($socket, $timeout);
    }
}
