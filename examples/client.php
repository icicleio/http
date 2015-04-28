<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Http\Client\Client;
use Icicle\Http\Encoder\Encoder;
use Icicle\Loop\Loop;

$coroutine = Coroutine::call(function () {
    $client = new Client();
    $encoder = new Encoder();

    /** @var \Icicle\Http\Message\ResponseInterface $response */
    $response = (yield $client->request('GET', 'http://www.google.com/teapot'));
    //$response = (yield $client->request('GET', 'http://localhost:8080/'));

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
    printf("Exception: %s\n", $exception->getMessage());
});

Loop::run();
