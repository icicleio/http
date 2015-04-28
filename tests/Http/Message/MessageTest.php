<?php
namespace Icicle\Tests\Http\Message;

use Icicle\Stream\ReadableStreamInterface;
use Icicle\Tests\TestCase;

class MessageTest extends TestCase
{
    /**
     * @return  \Icicle\Stream\ReadableStreamInterface
     */
    public function createStream()
    {
        return $this->getMock('Icicle\Stream\ReadableStreamInterface');
    }

    /**
     * @param   \Icicle\Stream\ReadableStreamInterface|null $stream
     * @param   array|null $headers
     * @param   string $protocol
     *
     * @return  \Icicle\Http\Message\Message
     */
    public function createMessage(ReadableStreamInterface $stream = null, array $headers = null, $protocol = '1.1')
    {
        return $this->getMockForAbstractClass('Icicle\Http\Message\Message', [$headers, $stream, $protocol]);
    }

    public function testGetProtocol()
    {
        $protocol = '1.0';

        $message = $this->createMessage(null, null, $protocol);

        $this->assertSame($protocol, $message->getProtocolVersion());
    }

    /**
     * @depends testGetProtocol
     */
    public function testWithProtocol()
    {
        $original = '1.1';
        $protocol = '1.0';

        $message = $this->createMessage(null, null, $original);
        $new = $message->withProtocolVersion($protocol);

        $this->assertNotSame($message, $new);
        $this->assertSame($protocol, $new->getProtocolVersion());
        $this->assertSame($original, $message->getProtocolVersion());
    }

    public function testGetBody()
    {
        $message = $this->createMessage();
        $this->assertInstanceOf('Icicle\Stream\ReadableStreamInterface', $message->getBody());
    }

    /**
     * @depends testGetBody
     */
    public function testStreamGivenToConstructorUsedAsBody()
    {
        $stream = $this->createStream();

        $message = $this->createMessage($stream);
        $this->assertSame($stream, $message->getBody());
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
        $this->assertSame($stream, $new->getBody());
        $this->assertNotSame($stream, $message->getBody());
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
     * @param   \Icicle\Http\Message\Message $message
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
     * @param   \Icicle\Http\Message\Message $message
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
     * @param   \Icicle\Http\Message\Message $message
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
        $this->assertSame(null, $message->getHeaderLine('Connection'));
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
     * @expectedException \Icicle\Http\Exception\InvalidArgumentException
     */
    public function testHeaderCreationWithArrayContainingNonString()
    {
        $headers = [
            'Host' => new \stdClass(),
        ];

        $this->createMessage(null, $headers);
    }

    /**
     * @expectedException \Icicle\Http\Exception\InvalidArgumentException
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

        $lines = [
            'Accept' => 'text/html,text/plain',
        ];

        $line = 'text/html,text/plain';

        $message = $this->createMessage(null, $headers);

        $this->assertSame($expected, $message->getHeaders());
        $this->assertSame($line, $message->getHeaderLine('Accept'));
        $this->assertSame($lines, $message->getHeaderLines());
    }

    public function testWithHeader()
    {
        $message = $this->createMessage();
        $new = $message->withHeader('Accept', 'text/html');

        $this->assertNotSame($message, $new);
        $this->assertSame('text/html', $new->getHeaderLine('Accept'));
        $this->assertSame(null, $message->getHeaderLine('Accept'));

        return $new;
    }

    /**
     * @depends testWithHeader
     * @param   \Icicle\Http\Message\Message $message
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
     * @param   \Icicle\Http\Message\Message $message
     */
    public function testWithAddedHeader($message)
    {
        $new = $message->withAddedHeader('Accept', 'text/plain');

        $expected = [
            'Accept' => ['text/html', 'text/plain'],
        ];

        $lines = [
            'Accept' => 'text/html,text/plain',
        ];

        $line = 'text/html,text/plain';

        $this->assertSame($expected, $new->getHeaders());
        $this->assertSame($line, $new->getHeaderLine('Accept'));
        $this->assertSame($lines, $new->getHeaderLines());
    }

    /**
     * @depends testWithHeader
     * @param   \Icicle\Http\Message\Message $message
     */
    public function testWithAddedHeaderKeepsOriginalCase($message)
    {
        $new = $message->withAddedHeader('ACCEPT', 'text/plain');
        $new = $new->withAddedHeader('accept', '*/*');

        $expected = [
            'Accept' => ['text/html', 'text/plain', '*/*'],
        ];

        $lines = [
            'Accept' => 'text/html,text/plain,*/*',
        ];

        $line = 'text/html,text/plain,*/*';

        $this->assertSame($expected, $new->getHeaders());
        $this->assertSame($line, $new->getHeaderLine('Accept'));
        $this->assertSame($lines, $new->getHeaderLines());
    }

    /**
     * @depends testWithHeader
     * @param   \Icicle\Http\Message\Message $message
     */
    public function testWithAddedHeaderOnPreviouslyUnsetHeader($message)
    {
        $new = $message->withAddedHeader('Connection', 'close');

        $expected = [
            'Accept' => ['text/html'],
            'Connection' => ['close'],
        ];

        $lines = [
            'Accept' => 'text/html',
            'Connection' => 'close',
        ];

        $this->assertSame($expected, $new->getHeaders());
        $this->assertSame($lines, $new->getHeaderLines());
    }

    /**
     * @depends testWithHeader
     * @param   \Icicle\Http\Message\Message $message
     */
    public function testWithoutHeader($message)
    {
        $new = $message->withoutHeader('Accept');

        $this->assertSame([], $new->getHeaders());
    }

    /**
     * @depends testWithHeader
     * @param   \Icicle\Http\Message\Message $message
     */
    public function testWithoutHeaderIsCaseInsensitive($message)
    {
        $new = $message->withoutHeader('ACCEPT');

        $this->assertSame([], $new->getHeaders());
    }

    /**
     * @depends testWithHeader
     * @param   \Icicle\Http\Message\Message $message
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
