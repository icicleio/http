<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\Response;
use Icicle\Http\Server\Server;
use Icicle\Loop\Loop;
use Icicle\Socket\Client\ClientInterface;

$server = new Server(function (RequestInterface $request, ClientInterface $client) {
    $data = sprintf(
        'Hello to %s:%d from %s:%d!',
        $client->getRemoteAddress(),
        $client->getRemotePort(),
        $client->getLocalAddress(),
        $client->getLocalPort()
    );

    $response = new Response(200);
    $response = $response->withHeader('Content-Type', 'text/plain');

    $response->getBody()->end($data);

    return $response;
});

$server->listen(8080);
$server->listen(8888);

$server->on('client-error', function (Exception $exception) {
    printf("Client error: %s\n", $exception->getMessage());
});

Loop::run();
