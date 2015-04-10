<?php

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('Icicle\\Tests\\', dirname(__DIR__) . '/vendor/icicleio/event-emitter/tests');
$loader->addPsr4('Icicle\\Tests\\', dirname(__DIR__) . '/vendor/icicleio/icicle/tests');
$loader->addPsr4('Icicle\\Tests\\', dirname(__DIR__) . '/vendor/icicleio/dns/tests');
$loader->addPsr4('Icicle\\Tests\\', __DIR__);
