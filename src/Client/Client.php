<?php
namespace Icicle\Http\Client;

use Icicle\Http\Driver\Http1Driver;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicRequest;
use Icicle\Socket\Socket;
use Icicle\Stream\ReadableStream;

class Client
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
     * {@inheritdoc}
     */
    public function request(
        Socket $socket,
        string $method,
        $uri,
        array $headers = [],
        ReadableStream $body = null,
        array $options = []
    ): \Generator {
        return $this->send($socket, new BasicRequest($method, $uri, $headers, $body), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Socket $socket, Request $request, array $options = []): \Generator
    {
        return $this->requester->request($socket, $request, $options);
    }
}
