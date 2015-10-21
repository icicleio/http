#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\Response;
use Icicle\Http\Server\RequestHandlerInterface;
use Icicle\Http\Server\Server;
use Icicle\Loop;
use Icicle\Socket\SocketInterface;
use Icicle\Stream\MemorySink;

class RequestHandler implements RequestHandlerInterface
{
    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param \Icicle\Socket\SocketInterface $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface $response
     */
    public function onRequest(RequestInterface $request, SocketInterface $socket)
    {
        $data = sprintf(
            'Hello to %s:%d from %s:%d!',
            $socket->getRemoteAddress(),
            $socket->getRemotePort(),
            $socket->getLocalAddress(),
            $socket->getLocalPort()
        );

        $body = $request->getBody();

        if ($body->isReadable()) {
            $data .= "\n\n";
            do {
                $data .= (yield $body->read());
            } while ($body->isReadable());
        }

        $sink = new MemorySink();
        yield $sink->end($data);

        $response = new Response(200, [
            'Content-Type' => 'text/plain',
            'Content-Length' => $sink->getLength(),
        ], $sink);

        yield $response;
    }

    /**
     * @param int $code
     * @param \Icicle\Socket\SocketInterface $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface
     */
    public function onError($code, SocketInterface $socket)
    {
        yield new Response($code);
    }
}

$server = new Server(new RequestHandler());

$server->listen(8080);
$server->listen(8888);

Loop\run();
