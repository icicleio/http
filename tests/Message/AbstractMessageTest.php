<?php
namespace Icicle\Tests\Http\Message;

use Icicle\Http\Message\AbstractMessage;
use Icicle\Stream\ReadableStream;
use Icicle\Tests\Http\TestCase;

class MessageTest extends TestCase
{
    /**
     * @return \Icicle\Stream\ReadableStream
     */
    public function createStream()
    {
        return $this->getMock(ReadableStream::class);
    }

    /**
     * @param \Icicle\Stream\ReadableStream|null $stream
     * @param array|null $headers
     * @param string $protocol
     *
     * @return \Icicle\Http\Message\Message
     */
    public function createMessage(ReadableStream $stream = null, array $headers = [], $protocol = '1.1')
    {
        return $this->getMockForAbstractClass(AbstractMessage::class, [$headers, $stream, $protocol]);
    }

    public function testGetProtocol()
    {
        $protocol = '1.0';

        $message = $this->createMessage(null, [], $protocol);

        $this->assertSame($protocol, $message->getProtocolVersion());
    }

    /**
     * @depends testGetProtocol
     */
    public function testWithProtocol()
    {
        $original = '1.1';
        $protocol = '1.0';

        $message = $this->createMessage(null, [], $original);
        $new = $message->withProtocolVersion($protocol);

        $this->assertNotSame($message, $new);
        $this->assertSame($protocol, $new->getProtocolVersion());
        $this->assertSame($original, $message->getProtocolVersion());
    }

    /**
     * @expectedException \Icicle\Http\Exception\UnsupportedVersionException
     */
    public function testCreateWithInvalidProtocol()
    {
        $protocol = 'protocol';

        $message = $this->createMessage(null, [], $protocol);
    }

    /**
     * @expectedException \Icicle\Http\Exception\UnsupportedVersionException
     */
    public function testWithInvalidProtocol()
    {
        $original = '1.1';
        $protocol = 'protocol';

        $message = $this->createMessage(null, [], $original);
        $new = $message->withProtocolVersion($protocol);
    }

    public function testGetBody()
    {
        $message = $this->createMessage();
        $this->assertInstanceOf(ReadableStream::class, $message->getBody());
    }

    /**
     * @depends testGetBody
     */
    public function testStreamGivenToConstructorUsedAsBody()
    {
        $stream = $this->createStream();

        $message = $this->createMessage($stream);
        $this->assertTrue($stream === $message->getBody());
    }

    /**
     * @depends testGetBody
     */
    public function testWithBody()
    {
        $stream = $this->createStream();

        $message = $this->createMessage();
        $new = $message->withBody($stream);

        $this->assertNotSame($message, $new);
        $this->assertTrue($stream === $new->getBody());
        $this->assertFalse($stream === $message->getBody());
    }

    public function testGetHeadersWhenNoHeadersProvided()
    {
        $message = $this->createMessage();

        $this->assertSame([], $message->getHeaders());
    }

    public function testHeaderCreationWithArrayOfStrings()
    {
        $headers = [
            'Host' => 'example.com:80',
            'Connection' => 'close',
        ];

        $expected = [
            'Host' => ['example.com:80'],
            'Connection' => ['close'],
        ];

        $message = $this->createMessage(null, $headers);

        $this->assertSame($expected, $message->getHeaders());

        return $message;
    }

    /**
     * @depends testHeaderCreationWithArrayOfStrings
     * @param \Icicle\Http\Message\Message $message
     */
    public function testHasHeaderCaseInsensitive($message)
    {
        $this->assertTrue($message->hasHeader('host'));
        $this->assertTrue($message->hasHeader('HOST'));
        $this->assertTrue($message->hasHeader('connection'));
        $this->assertTrue($message->hasHeader('CONNECTION'));
    }

    /**
     * @depends testHeaderCreationWithArrayOfStrings
     * @param \Icicle\Http\Message\Message $message
     */
    public function testGetHeaderCaseInsensitive($message)
    {
        $this->assertSame(['example.com:80'], $message->getHeader('host'));
        $this->assertSame(['example.com:80'], $message->getHeader('host'));
        $this->assertSame(['close'], $message->getHeader('connection'));
        $this->assertSame(['close'], $message->getHeader('CONNECTION'));
    }

    /**
     * @depends testHeaderCreationWithArrayOfStrings
     * @param \Icicle\Http\Message\Message $message
     */
    public function testGetHeaderLineCaseInsensitive($message)
    {
        $this->assertSame('example.com:80', $message->getHeaderLine('host'));
        $this->assertSame('example.com:80', $message->getHeaderLine('host'));
        $this->assertSame('close', $message->getHeaderLine('connection'));
        $this->assertSame('close', $message->getHeaderLine('CONNECTION'));
    }

    public function testNonExistentHeader()
    {
        $message = $this->createMessage();

        $this->assertFalse($message->hasHeader('Connection'));
        $this->assertSame([], $message->getHeader('Connection'));
        $this->assertSame('', $message->getHeaderLine('Connection'));
    }

    public function testHeaderCreationWithArrayOfArrayOfStrings()
    {
        $headers = [
            'Host' => ['example.com:80'],
            'Connection' => ['close'],
        ];

        $message = $this->createMessage(null, $headers);

        $this->assertSame($headers, $message->getHeaders());
    }

    /**
     * @expectedException \Icicle\Http\Exception\InvalidHeaderException
     */
    public function testHeaderCreationWithArrayContainingNonString()
    {
        $headers = [
            'Host' => new \stdClass(),
        ];

        $this->createMessage(null, $headers);
    }

    /**
     * @expectedException \Icicle\Http\Exception\InvalidHeaderException
     */
    public function testHeaderCreationWithArrayOfArraysContainingNonString()
    {
        $headers = [
            'Host' => [new \stdClass()],
        ];

        $this->createMessage(null, $headers);
    }

    public function testHeaderCreationWithSimilarlyNamedKeys()
    {
        $headers = [
            'Accept' => 'text/html',
            'accept' => 'text/plain',
        ];

        $expected = [
            'Accept' => ['text/html', 'text/plain'],
        ];

        $line = 'text/html,text/plain';

        $message = $this->createMessage(null, $headers);

        $this->assertSame($expected, $message->getHeaders());
        $this->assertSame($line, $message->getHeaderLine('Accept'));
    }

    public function testWithHeader()
    {
        $message = $this->createMessage();
        $new = $message->withHeader('Accept', 'text/html');

        $this->assertNotSame($message, $new);
        $this->assertSame('text/html', $new->getHeaderLine('Accept'));
        $this->assertSame('', $message->getHeaderLine('Accept'));

        return $new;
    }

    /**
     * @expectedException \Icicle\Http\Exception\InvalidHeaderException
     */
    public function testWithHeaderNonStringValue()
    {
        $message = $this->createMessage();

        $new = $message->withHeader('Content-Length', 100);

        $this->assertSame('100', $new->getHeaderLine('Content-Length'));

        $new = $message->withHeader('Null-Value-Header', null);

        $this->assertSame('', $new->getHeaderLine('Null-Value-Header'));

        $new = $message->withHeader('Invalid-Type', new \stdClass());
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     */
    public function testWithHeaderUsesLastCase($message)
    {
        $new = $message->withHeader('accept', 'text/plain');

        $expected = [
            'accept' => ['text/plain'],
        ];

        $this->assertSame($expected, $new->getHeaders());
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     * @expectedException \Icicle\Http\Exception\InvalidHeaderException
     */
    public function testWithHeaderInvalidName($message)
    {
        $message->withHeader('Invalid-∂', 'value');
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     * @expectedException \Icicle\Http\Exception\InvalidHeaderException
     */
    public function testWithHeaderInvalidValue($message)
    {
        $message->withHeader('Invalid-Value', "va\0lue");
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     */
    public function testWithHeaderNumericValue($message)
    {
        $new = $message->withHeader('Content-Length', 123);

        $this->assertSame(['123'], $new->getHeader('Content-Length'));
        $this->assertSame('123', $new->getHeaderLine('Content-Length'));
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     */
    public function testWithAddedHeader($message)
    {
        $new = $message->withAddedHeader('Accept', 'text/plain');

        $expected = [
            'Accept' => ['text/html', 'text/plain'],
        ];

        $line = 'text/html,text/plain';

        $this->assertSame($expected, $new->getHeaders());
        $this->assertSame($line, $new->getHeaderLine('Accept'));
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     * @expectedException \Icicle\Http\Exception\InvalidHeaderException
     */
    public function testWithAddedHeaderNonStringValue($message)
    {
        $new = $message->withAddedHeader('Content-Length', 100);

        $this->assertSame('100', $new->getHeaderLine('Content-Length'));

        $new = $message->withAddedHeader('Accept', null);

        $expected = ['text/html', ''];

        $line = 'text/html,';

        $this->assertSame($expected, $new->getHeader('Accept'));
        $this->assertSame($line, $new->getHeaderLine('Accept'));

        $new = $message->withAddedHeader('Invalid-Type', new \stdClass());
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     */
    public function testWithAddedHeaderKeepsOriginalCase($message)
    {
        $new = $message->withAddedHeader('ACCEPT', 'text/plain');
        $new = $new->withAddedHeader('accept', '*/*');

        $expected = [
            'Accept' => ['text/html', 'text/plain', '*/*'],
        ];

        $line = 'text/html,text/plain,*/*';

        $this->assertSame($expected, $new->getHeaders());
        $this->assertSame($line, $new->getHeaderLine('Accept'));
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     */
    public function testWithAddedHeaderOnPreviouslyUnsetHeader($message)
    {
        $new = $message->withAddedHeader('Connection', 'close');

        $expected = [
            'Accept' => ['text/html'],
            'Connection' => ['close'],
        ];

        $this->assertSame($expected, $new->getHeaders());
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     * @expectedException \Icicle\Http\Exception\InvalidHeaderException
     */
    public function testWithAddedHeaderInvalidName($message)
    {
        $message->withAddedHeader('Invalid-∂', 'value');
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     * @expectedException \Icicle\Http\Exception\InvalidHeaderException
     */
    public function testWithAddedHeaderInvalidValue($message)
    {
        $message->withAddedHeader('Invalid-Value', "va\0lue");
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     */
    public function testWithAddedHeaderNumericValue($message)
    {
        $new = $message->withAddedHeader('Content-Length', 321);

        $this->assertSame(['321'], $new->getHeader('Content-Length'));
        $this->assertSame('321', $new->getHeaderLine('Content-Length'));
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     */
    public function testWithoutHeader($message)
    {
        $new = $message->withoutHeader('Accept');

        $this->assertSame([], $new->getHeaders());
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     */
    public function testWithoutHeaderIsCaseInsensitive($message)
    {
        $new = $message->withoutHeader('ACCEPT');

        $this->assertSame([], $new->getHeaders());
    }

    /**
     * @depends testWithHeader
     * @param \Icicle\Http\Message\Message $message
     */
    public function testWithoutHeaderOnPreviouslyUnsetHeader($message)
    {
        $new = $message->withoutHeader('Connection');

        $expected = [
            'Accept' => ['text/html'],
        ];

        $this->assertSame($expected, $new->getHeaders());
    }
}
