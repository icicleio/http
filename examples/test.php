<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Http\Uri;

$uri = new Uri('https://icicle.io:443/test%20path');

//$uri = $uri->withPath('/different/path');

$uri = $uri->withPort(80);

$uri = $uri->withScheme('http');

echo $uri . PHP_EOL;
