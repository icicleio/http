<?php
namespace Icicle\Tests\Http\Parser;

use Icicle\Coroutine\Coroutine;
use Icicle\Http\Parser\Parser;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Stream\SeekableStreamInterface;
use Icicle\Tests\TestCase;
use Mockery;
use Symfony\Component\Yaml\Yaml;

class ParserTest extends TestCase
{
    /**
     * @var \Icicle\Http\Parser\Parser;
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser();
    }

    /**
     * @return  \Icicle\Stream\ReadableStreamInterface
     */
    public function createStream()
    {
        return $mock = Mockery::mock('Icicle\Stream\ReadableStreamInterface');
    }

    /**
     * @param   string $filename
     *
     * @return  string
     */
    protected function readMessage($filename)
    {
        return file_get_contents(dirname(__DIR__) . '/data/' . $filename);
    }

    /**
     * @return array
     */
    public function getValidRequests()
    {
        return Yaml::parse(file_get_contents(dirname(__DIR__) . '/data/requests/valid.yml'));
    }

    /**
     * @dataProvider getValidRequests
     * @param   string $filename
     * @param   string $method
     * @param   string $target
     * @param   string $protocolVersion
     * @param   string[][] $headers
     * @param   string|null $body
     */
    public function testParseRequest($filename, $method, $target, $protocolVersion, $headers, $body = null)
    {
        $message = $this->readMessage($filename);

        $request = $this->parser->parseRequest($message);

        $this->assertSame($method, $request->getMethod());
        $this->assertSame($target, $request->getRequestTarget());
        $this->assertSame($protocolVersion, $request->getProtocolVersion());
        $this->assertEquals($headers, $request->getHeaders());

        if (null !== $body) { // Check body only if not null.
            $stream = $request->getBody();

            if ($stream instanceof SeekableStreamInterface) {
                $stream->seek(0);
                $this->assertSame(strlen($body), $stream->getLength());
            }

            $promise = $stream->read();

            $callback = $this->createCallback(1);
            $callback->method('__invoke')
                ->with($this->identicalTo($body));

            $promise->done($callback, $this->createCallback(0));

            Loop::run();
        }
    }

    /**
     * @return array
     */
    public function getInvalidRequests()
    {
        return Yaml::parse(file_get_contents(dirname(__DIR__) . '/data/requests/invalid.yml'));
    }

    /**
     * @dataProvider getInvalidRequests
     * @param   string $filename
     * @param   string $exceptionClass
     */
    public function testParseInvalidRequest($filename, $exceptionClass)
    {
        $message = $this->readMessage($filename);
        $this->setExpectedException($exceptionClass);

        $this->parser->parseRequest($message);
    }

    /**
     * @depends testParseRequest
     * @expectedException \Icicle\Http\Exception\LogicException
     */
    public function testParseRequestProvidingStreamWithBody()
    {
        $stream = $this->createStream();
        $message = "POST / HTTP/1.1\r\nHost: example.com\r\n\r\nRequest body.";

        $this->parser->parseRequest($message, $stream);
    }

    /**
     * @return array
     */
    public function getValidResponses()
    {
        return Yaml::parse(file_get_contents(dirname(__DIR__) . '/data/responses/valid.yml'));
    }

    /**
     * @dataProvider getValidResponses
     * @param   string $filename
     * @param   int $code
     * @param   string $reason
     * @param   string $protocolVersion
     * @param   string[][] $headers
     * @param   string|null $body
     */
    public function testParseResponse($filename, $code, $reason, $protocolVersion, $headers, $body = null)
    {
        $message = $this->readMessage($filename);

        $response = $this->parser->parseResponse($message);

        $this->assertSame($code, $response->getStatusCode());
        $this->assertSame($reason, $response->getReasonPhrase());
        $this->assertSame($protocolVersion, $response->getProtocolVersion());
        $this->assertEquals($headers, $response->getHeaders());

        if (null !== $body) { // Check body only if not null.
            $stream = $response->getBody();

            if ($stream instanceof SeekableStreamInterface) {
                $stream->seek(0);
                $this->assertSame(strlen($body), $stream->getLength());
            }

            $promise = $stream->read();

            $callback = $this->createCallback(1);
            $callback->method('__invoke')
                ->with($this->identicalTo($body));

            $promise->done($callback, $this->createCallback(0));

            Loop::run();
        }
    }

    /**
     * @return array
     */
    public function getInvalidResponses()
    {
        return Yaml::parse(file_get_contents(dirname(__DIR__) . '/data/responses/invalid.yml'));
    }

    /**
     * @dataProvider getInvalidResponses
     * @param   string $filename
     * @param   string $exceptionClass
     */
    public function testParseInvalidResponse($filename, $exceptionClass)
    {
        $message = $this->readMessage($filename);
        $this->setExpectedException($exceptionClass);

        $this->parser->parseResponse($message);
    }


    /**
     * @depends testParseResponse
     * @expectedException \Icicle\Http\Exception\LogicException
     */
    public function testParseResponseProvidingStreamWithBody()
    {
        $stream = $this->createStream();
        $message = "HTTP/1.1 200 OK\r\n\r\nResponse body.";

        $this->parser->parseResponse($message, $stream);
    }

    public function testReadMessage()
    {
        $stream = $this->createStream();
        $maxSize = 8192;
        $parts = [
            "HTTP/1.1 200 OK\r\n",
            "Connection: close\r\n",
            "\r\n"
        ];
        $promises = array_map(function ($value) { return Promise::resolve($value); }, $parts);

        $stream->shouldReceive('read')
            ->andReturnValues($promises);

        $coroutine = new Coroutine($this->parser->readMessage($stream, $maxSize));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(implode('', $parts)));

        $coroutine->done($callback, $this->createCallback(0));

        Loop::run();
    }

    /**
     * @depends testReadMessage
     */
    public function testReadMessageMaxSize()
    {
        $stream = $this->createStream();
        $maxSize = 1;

        $stream->shouldReceive('read')
            ->andReturn(Promise::resolve("HTTP/1.1 200 OK\r\n\r\n"));

        $coroutine = new Coroutine($this->parser->readMessage($stream, $maxSize));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Http\Exception\MessageHeaderSizeException'));

        $coroutine->done($this->createCallback(0), $callback);

        Loop::run();
    }
}