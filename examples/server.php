#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\Response;
use Icicle\Http\Server\Server;
use Icicle\Loop;
use Icicle\Socket\Client\ClientInterface;

$server = new Server(function (RequestInterface $request, ClientInterface $client) {
    $data = sprintf(
        'Hello to %s:%d from %s:%d!',
        $client->getRemoteAddress(),
        $client->getRemotePort(),
        $client->getLocalAddress(),
        $client->getLocalPort()
    );

    $body = $request->getBody();

    if ($body->isReadable()) {
        $data .= "\n\n";
        do {
            $data .= (yield $body->read());
        } while ($body->isReadable());
    }

    $response = new Response(200);
    $response = $response->withHeader('Content-Type', 'text/plain')
        ->withHeader('Content-Length', strlen($data));

    yield $response->getBody()->end($data);

    yield $response;
});

$server->listen(8080);
$server->listen(8888);

Loop\run();
