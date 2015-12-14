#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\Http\Client\Client;
use Icicle\Http\Driver\Encoder\Http1Encoder;
use Icicle\Loop;
use Icicle\Socket;

$coroutine = Coroutine\create(function () {
    $client = new Client();
    $encoder = new Http1Encoder();

    // Connect to a google.com IP.
    // Use Icicle\Dns\connect() in icicleio/dns package to resolve and connect using domain names.
    $socket = (yield Socket\connect('173.194.46.70', 80));

    /** @var \Icicle\Http\Message\Response $response */
    $response = (yield $client->request($socket, 'GET', 'http://www.google.com/teapot'));

    printf("Headers:\n%s", $encoder->encodeResponse($response));

    printf("Body:\n");

    $stream = $response->getBody();

    while ($stream->isReadable()) {
        $data = (yield $stream->read());
        echo $data;
    }

    echo "\n";
});

$coroutine->done(null, function (Exception $exception) {
    printf("Exception: %s\n", $exception);
});

Loop\run();
