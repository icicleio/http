# HTTP for Icicle

**Asynchronous, non-blocking HTTP/1.1 client and server.**

This library is a component for [Icicle](https://github.com/icicleio/icicle) that provides an HTTP/1.1 server and client implementations. Like other Icicle components, this library uses [Promises](https://github.com/icicleio/icicle/wiki/Promises) and [Generators](http://www.php.net/manual/en/language.generators.overview.php) for asynchronous operations that may be used to build [Coroutines](https://github.com/icicleio/icicle/wiki/Coroutines) to make writing asynchronous code more like writing synchronous code.

[![Build Status](https://img.shields.io/travis/icicleio/http/master.svg?style=flat-square)](https://travis-ci.org/icicleio/http)
[![Coverage Status](https://img.shields.io/coveralls/icicleio/http/master.svg?style=flat-square)](https://coveralls.io/r/icicleio/http)
[![Semantic Version](https://img.shields.io/github/release/icicleio/http.svg?style=flat-square)](http://semver.org)
[![MIT License](https://img.shields.io/packagist/l/icicleio/http.svg?style=flat-square)](LICENSE)
[![@icicleio on Twitter](https://img.shields.io/badge/twitter-%40icicleio-5189c7.svg?style=flat-square)](https://twitter.com/icicleio)

##### Requirements

- PHP 5.5+

##### Suggested

- [openssl extension](http://php.net/manual/en/book.openssl.php): Required to create HTTPS servers or make requests over HTTPS.

##### Installation

The recommended way to install is with the [Composer](http://getcomposer.org/) package manager. (See the [Composer installation guide](https://getcomposer.org/doc/00-intro.md) for information on installing and using Composer.)

Run the following command to use this library in your project: 

```bash
composer require icicleio/http
```

You can also manually edit `composer.json` to add this library as a project requirement.

```js
// composer.json
{
    "require": {
        "icicleio/http": "^0.2"
    }
}
```

#### Example

The example below creates a simple HTTP server that responds with `Hello, world!` to every request.

```php
#!/usr/bin/env php
<?php

require '/vendor/autoload.php';

use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\Response;
use Icicle\Http\Server\Server;
use Icicle\Loop;

$server = new Server(function (RequestInterface $request) {
    $response = new Response(200);
    yield $response->getBody()->end('Hello, world!');
    yield $response->withHeader('Content-Type', 'text/plain');
});

$server->listen(8080);

echo "Server running at http://127.0.0.1:8080\n";

Loop\run();
```

**More documentation coming soon...**
