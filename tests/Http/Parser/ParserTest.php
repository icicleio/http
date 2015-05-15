<?php
namespace Icicle\Tests\Http\Parser;

use Icicle\Http\Parser\Parser;
use Icicle\Loop\Loop;
use Icicle\Stream\SeekableStreamInterface;
use Icicle\Tests\TestCase;
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
     * @param   string $filename
     *
     * @return  string
     */
    protected function readMessage($filename)
    {
        return file_get_contents(dirname(dirname(__DIR__)) . '/data/' . $filename);
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
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/requests/invalid.yml'));
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
     * @return array
     */
    public function getValidResponses()
    {
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/responses/valid.yml'));
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
        return Yaml::parse(file_get_contents(dirname(dirname(__DIR__)) . '/data/responses/invalid.yml'));
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
}