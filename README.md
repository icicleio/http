# HTTP for Icicle

**Asynchronous, non-blocking HTTP/1.1 client and server.**

This library is a component for [Icicle](https://github.com/icicleio/icicle) that provides an HTTP/1.1 server and client implementations. Like other Icicle components, this library uses [Coroutines](https://icicle.io/docs/manual/coroutines/) built from [Awaitables](https://icicle.io/docs/manual/awaitables/) and [Generators](http://www.php.net/manual/en/language.generators.overview.php) to make writing asynchronous code more like writing synchronous code.

[![Build Status](https://img.shields.io/travis/icicleio/http/v1.x.svg?style=flat-square)](https://travis-ci.org/icicleio/http)
[![Coverage Status](https://img.shields.io/coveralls/icicleio/http/v1.x.svg?style=flat-square)](https://coveralls.io/r/icicleio/http)
[![Semantic Version](https://img.shields.io/github/release/icicleio/http.svg?style=flat-square)](http://semver.org)
[![MIT License](https://img.shields.io/packagist/l/icicleio/http.svg?style=flat-square)](LICENSE)
[![@icicleio on Twitter](https://img.shields.io/badge/twitter-%40icicleio-5189c7.svg?style=flat-square)](https://twitter.com/icicleio)

#### Documentation and Support

- [Full API Documentation](https://icicle.io/docs)
- [Official Twitter](https://twitter.com/icicleio)
- [Gitter Chat](https://gitter.im/icicleio/icicle)

##### Requirements

- PHP 5.5+ for v0.3.x branch (current stable) and v1.x branch (mirrors current stable)
- PHP 7 for v2.0 (master) branch supporting generator delegation and return expressions

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
        "icicleio/http": "^0.3"
    }
}
```

#### Example

The example below creates a simple HTTP server that responds with `Hello, world!` to every request.

```php
#!/usr/bin/env php
<?php

require '/vendor/autoload.php';

use Icicle\Http\Message\{BasicResponse, Request, Response};
use Icicle\Http\Server\{RequestHandler, Server};
use Icicle\Socket\Socket;
use Icicle\Loop;

$server = new Server(new class implements RequestHandler {
    public function onRequest(Request $request, Socket $socket)
    {
        $response = new BasicResponse(Response::OK, [
            'Content-Type' => 'text/plain',
        ]);
        
        yield from $response->getBody()->end('Hello, world!');
        
        yield $response;
    }
    
    public function onError($code, Socket $socket)
    {
        return new BasicResponse($code);
    }
});

$server->listen(8080);

Loop\run();
```
