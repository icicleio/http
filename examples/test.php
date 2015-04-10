<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Http\Uri;

$uri = new Uri('http://icicle.io/test/path');

$uri = $uri->withPath('/different/path with spaces');

$uri = $uri->withScheme('https');

$uri = $uri->withQuery('test=value#awesome');

$uri = $uri->withQueryValue('name', 'value');

$uri = $uri->withoutQueryValue('test');

$uri = $uri->withoutQueryValue('unknown');

$uri = $uri->withFragment('test-fragment');

echo $uri . PHP_EOL;
