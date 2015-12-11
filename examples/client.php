#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\Dns;
use Icicle\Http\Client\Client;
use Icicle\Http\Driver\Encoder\Http1Encoder;
use Icicle\Loop;

$coroutine = Coroutine\create(function () {
    $client = new Client();
    $encoder = new Http1Encoder();

    $socket = (yield Dns\connect('www.google.com', 443));

    /** @var \Icicle\Http\Message\Response $response */
    $response = (yield $client->request($socket, 'GET', 'https://www.google.com/teapot'));

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
