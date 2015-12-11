<?php
namespace Icicle\Http\Client;

use Icicle\Dns;
use Icicle\Http\Driver\Http1Driver;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicRequest;
use Icicle\Socket\Socket;
use Icicle\Stream\ReadableStream;

class Client
{
    /**
     * @var \Icicle\Http\Client\Requester
     */
    private $requester;

    /**
     */
    public function __construct()
    {
        $this->requester = new Requester(new Http1Driver());
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function send(Socket $socket, Request $request, array $options = [])
    {
        return $this->requester->request($socket, $request, $options);
    }
}
