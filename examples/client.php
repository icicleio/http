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

    /** @var \Icicle\Http\Message\Response $response */
    $response = yield from $client->request('GET', 'http://www.google.com/teapot');

    printf("Headers:\n%s", $encoder->encodeResponse($response));

    printf("Body:\n");

    $stream = $response->getBody();

    while ($stream->isReadable()) {
        $data = yield from $stream->read();
        echo $data;
    }

    echo "\n";
});

$coroutine->done(null, function (Throwable $exception) {
    printf("Exception: %s\n", $exception);
});

Loop\run();
