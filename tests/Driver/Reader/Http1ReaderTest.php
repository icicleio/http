<?php
namespace Icicle\Tests\Http\Reader;

use Icicle\Coroutine\Coroutine;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Http\Driver\Reader\Http1Reader;
use Icicle\Loop;
use Icicle\Stream;
use Icicle\Socket\Socket;
use Icicle\Tests\Http\TestCase;
use Mockery;
use Symfony\Component\Yaml\Yaml;

class Http1ReaderTest extends TestCase
{
    /**
     * @return \Icicle\Stream\ReadableStream
     */
    protected function createSocket()
    {
        $socket = Mockery::mock(Socket::class);
        $socket->shouldIgnoreMissing();
        return $socket;
    }

    /**
     * @param string $filename
     *
     * @return \Icicle\Socket\Socket
     */
    protected function readMessage($filename)
    {
        $data = file_get_contents(dirname(dirname(__DIR__)) . '/data/' . $filename);

        $socket = $this->createSocket();

        $socket->shouldReceive('read')
            ->andReturnUsing(function () use (&$data) {
                yield $data;
            });

        $socket->shouldReceive('unshift')
            ->andReturnUsing(function ($string) use (&$data) {
                $data = $string;
            });

        return $socket;
    }

    /**
     * @return array
     */
    public function getValidRequests()
    {
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/requests/valid.yml'));
    }

    /**
     * @dataProvider getValidRequests
     * @param string $filename
     * @param string $method
     * @param string $target
     * @param string $protocolVersion
     * @param string[][] $headers
     * @param string|null $body
     */
    public function testReadRequest($filename, $method, $target, $protocolVersion, $headers, $body = null)
    {
        $reader = new Http1Reader();

        $socket = $this->readMessage($filename);

        $promise = new Coroutine($reader->readRequest($socket));

        $promise->done(function (Request $request) use (
            $method, $target, $protocolVersion, $headers, $body
        ) {
            $this->assertSame($method, $request->getMethod());
            $this->assertSame($target, (string) $request->getRequestTarget());
            $this->assertSame($protocolVersion, $request->getProtocolVersion());
            $this->assertEquals($headers, $request->getHeaders());

            if (null !== $body) { // Check body only if not null.
                $stream = $request->getBody();

                $promise = new Coroutine($stream->read());

                $callback = $this->createCallback(1);
                $callback->method('__invoke')
                    ->with($this->identicalTo($body));

                $promise->done($callback, $this->createCallback(0));
            }
        });

        Loop\run();
    }

    /**
     * @return array
     */
    public function getInvalidRequests()
    {
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/requests/invalid.yml'));
    }

    /**
     * @dataProvider getInvalidRequests
     * @param string $filename
     * @param string $exceptionClass
     */
    public function testReadInvalidRequest($filename, $exceptionClass)
    {
        $reader = new Http1Reader();

        $socket = $this->readMessage($filename);

        $promise = new Coroutine($reader->readRequest($socket));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf($exceptionClass));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @return array
     */
    public function getValidResponses()
    {
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/responses/valid.yml'));
    }

    /**
     * @dataProvider getValidResponses
     * @param string $filename
     * @param int $code
     * @param string $reason
     * @param string $protocolVersion
     * @param string[][] $headers
     * @param string|null $body
     */
    public function testReadResponse($filename, $code, $reason, $protocolVersion, $headers, $body = null)
    {
        $reader = new Http1Reader();

        $socket = $this->readMessage($filename);

        $promise = new Coroutine($reader->readResponse($socket));

        $promise->done(function (Response $response) use (
            $code, $reason, $protocolVersion, $headers, $body
        ) {
            $this->assertSame($code, $response->getStatusCode());
            $this->assertSame($reason, $response->getReasonPhrase());
            $this->assertSame($protocolVersion, $response->getProtocolVersion());
            $this->assertEquals($headers, $response->getHeaders());

            if (null !== $body) { // Check body only if not null.
                $stream = $response->getBody();

                $promise = new Coroutine($stream->read());

                $callback = $this->createCallback(1);
                $callback->method('__invoke')
                    ->with($this->identicalTo($body));

                $promise->done($callback, $this->createCallback(0));
            }
        });

        Loop\run();
    }

    /**
     * @return array
     */
    public function getInvalidResponses()
    {
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/responses/invalid.yml'));
    }

    /**
     * @dataProvider getInvalidResponses
     * @param string $filename
     * @param string $exceptionClass
     */
    public function testReadInvalidResponse($filename, $exceptionClass)
    {
        $reader = new Http1Reader();

        $socket = $this->readMessage($filename);

        $promise = new Coroutine($reader->readResponse($socket));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf($exceptionClass));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @depends testReadRequest
     */
    public function testReadRequestMaxSizeExceeded()
    {
        $reader = new Http1Reader(['max_header_size' => 1]);

        $socket = $this->createSocket();
        $maxSize = 1;

        $socket->shouldReceive('read')
            ->andReturnUsing(
                function () {
                    yield "GET / HTTP/1.1\r\n";
                },
                function () {
                    yield "Host: example.com\r\n";
                },
                function () {
                    yield "\r\n";
                }
            );

        $promise = new Coroutine($reader->readRequest($socket, $maxSize));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(MessageException::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @depends testReadResponse
     */
    public function testReadResponseMaxSizeExceeded()
    {
        $reader = new Http1Reader(['max_header_size' => 1]);

        $socket = $this->createSocket();
        $maxSize = 1;

        $socket->shouldReceive('read')
            ->andReturnUsing(
                function () {
                    yield "HTTP/1.1 200 OK\r\n";
                },
                function () {
                    yield "Connection: close\r\n";
                },
                function () {
                    yield "\r\n";
                }
            );

        $promise = new Coroutine($reader->readResponse($socket, $maxSize));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(MessageException::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }
}