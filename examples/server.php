#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Http\Message\Request;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Server\RequestHandler;
use Icicle\Http\Server\Server;
use Icicle\Loop;
use Icicle\Socket\Socket;
use Icicle\Stream\MemorySink;

class ExampleRequestHandler implements RequestHandler
{
    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response $response
     */
    public function onRequest(Request $request, Socket $socket)
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

        $response = new BasicResponse(200, [
            'Content-Type' => 'text/plain',
            'Content-Length' => $sink->getLength(),
        ], $sink);

        yield $response;
    }

    /**
     * @param int $code
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Icicle\Http\Message\Response
     */
    public function onError($code, Socket $socket)
    {
        return new BasicResponse($code);
    }
}

$server = new Server(new ExampleRequestHandler());

$server->listen(8080);
$server->listen(8888);

Loop\run();
