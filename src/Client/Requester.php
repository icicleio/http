<?php
namespace Icicle\Http\Client;

use Icicle\Http\Driver\Http1Driver;
use Icicle\Http\Message\{Request, BasicRequest};
use Icicle\Socket\Socket;
use Icicle\Stream\ReadableStream;

class Requester
{
    /**
     * @var \Icicle\Http\Client\Internal\Requester
     */
    private $requester;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        $this->requester = new Internal\Requester(new Http1Driver($options));
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
        $method,
        $uri,
        array $headers = [],
        ReadableStream $body = null,
        array $options = []
    ) {
        return $this->send($socket, new BasicRequest($method, $uri, $headers, $body), $options);
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
    public function send(Socket $socket, Request $request, array $options = [])
    {
        return $this->requester->send($socket, $request, $options);
    }
}
