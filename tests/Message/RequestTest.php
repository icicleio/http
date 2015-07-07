<?php
namespace Icicle\Tests\Http\Message;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Uri;
use Icicle\Tests\Http\TestCase;

class RequestTest extends TestCase
{
    public function getInvalidMethods()
    {
        return [
            [null], // null
            [100], // integer
            [3.14], // float
            [['GET']], // array
            [new \stdClass()], // object
        ];
    }

    public function getValidMethods()
    {
        return [
            ['GET'],
            ['POST'],
            ['PUT'],
            ['DELETE'],
            ['OPTIONS'],
            ['HEAD'],
            ['CONNECT'],
            ['PATCH'],
            ['TRACE'],
        ];
    }

    /**
     * @expectedException \Icicle\Http\Exception\InvalidMethodException
     * @dataProvider getInvalidMethods
     * @param string $method
     */
    public function testInvalidMethodThrowsException($method)
    {
        new Request($method);
    }

    /**
     * @dataProvider getValidMethods
     * @param string $method
     */
    public function testConstructWithValidMethod($method)
    {
        $request = new Request($method);
        $this->assertSame($method, $request->getMethod());
    }

    /**
     * @expectedException \Icicle\Http\Exception\InvalidMethodException
     * @dataProvider getInvalidMethods
     * @param string $method
     */
    public function testWithMethodThrowsExceptionWithInvalidMethod($method)
    {
        $request = new Request('GET');
        $request->withMethod($method);
    }

    /**
     * @dataProvider getValidMethods
     * @param string $method
     */
    public function testWithMethod($method)
    {
        $request = new Request('GET');
        $new = $request->withMethod($method);
        $this->assertNotSame($request, $new);
        $this->assertSame($method, $new->getMethod());
    }

    public function testUriWithoutHostDoesNotSetHostHeader()
    {
        $request = new Request('GET', new Uri('/path'));
        $this->assertFalse($request->hasHeader('Host'));
    }

    public function testUriWithHostSetsHostHeader()
    {
        $request = new Request('GET', new Uri('http://example.com:8080/path'));
        $this->assertTrue($request->hasHeader('Host'));
        $this->assertSame('example.com:8080', $request->getHeaderLine('Host'));
    }

    /**
     * @return \Icicle\Http\Message\Request
     */
    public function testUriDoesNotSetHostIfHostHeaderProvided()
    {
        $headers = [
            'Host' => 'example.net:8080',
        ];

        $request = new Request('GET', new Uri('http://example.com/path'), $headers);

        $this->assertTrue($request->hasHeader('Host'));
        $this->assertSame('example.net:8080', $request->getHeaderLine('Host'));

        return $request;
    }

    /**
     * @depends testUriDoesNotSetHostIfHostHeaderProvided
     * @param \Icicle\Http\Message\Request $request
     *
     * @return \Icicle\Http\Message\Request
     */
    public function testRemovingHostHeaderPullsHostFromUri($request)
    {
        $new = $request->withoutHeader('Host');
        $this->assertTrue($new->hasHeader('Host'));
        $this->assertSame('example.com:80', $new->getHeaderLine('Host'));

        return $new;
    }

    /**
     * @depends testRemovingHostHeaderPullsHostFromUri
     * @param \Icicle\Http\Message\Request $request
     */
    public function testWithHostHeaderOverridesHostSetFromUri($request)
    {
        $new = $request->withHeader('Host', 'example.org:443');
        $this->assertTrue($new->hasHeader('Host'));
        $this->assertSame('example.org:443', $new->getHeaderLine('Host'));
    }

    /**
     * @depends testRemovingHostHeaderPullsHostFromUri
     * @param \Icicle\Http\Message\Request $request
     */
    public function testWithAddedHostHeaderOverridesHostSetFromUri($request)
    {
        $new = $request->withAddedHeader('Host', 'example.org:443');
        $this->assertTrue($new->hasHeader('Host'));
        $this->assertSame('example.org:443', $new->getHeaderLine('Host'));

        $new = $new->withAddedHeader('Host', 'example.org:80');
        $this->assertSame('example.org:443,example.org:80', $new->getHeaderLine('Host'));
    }

    public function testWithUri()
    {
        $original = new Uri('http://example.com');
        $substitute = new Uri('http://example.net');

        $request = new Request('GET', $original);
        $new = $request->withUri($substitute);
        $this->assertNotSame($request, $new);
        $this->assertSame($original, $request->getUri());
        $this->assertSame($substitute, $new->getUri());
    }

    public function testWithUriWithString()
    {
        $original = 'http://example.com';
        $substitute = 'http://example.net';

        $request = new Request('GET', $original);
        $new = $request->withUri($substitute);
        $this->assertNotSame($request, $new);
        $this->assertSame($original, (string) $request->getUri());
        $this->assertSame($substitute, (string) $new->getUri());
    }

    /**
     * @depends testWithUri
     * @depends testUriWithHostSetsHostHeader
     */
    public function testWithUriSetsHostHeader()
    {
        $request = new Request('GET', new Uri('http://example.com'));
        $new = $request->withUri(new Uri('http://example.net'));

        $this->assertTrue($new->hasHeader('Host'));
        $this->assertSame('example.net:80', $new->getHeaderLine('Host'));
    }

    /**
     * @expectedException \Icicle\Http\Exception\InvalidValueException
     */
    public function testConstructWithInvalidTarget()
    {
        new Request('GET', '', null, null, 'Invalid target');
    }

    public function getUris()
    {
        return [
            ['http://example.com', '/'],
            ['http://example.com/', '/'],
            ['http://example.com/path', '/path'],
            ['http://example.com/path?name=value', '/path?name=value'],
            ['/path', '/path'],
            ['/path?name=value', '/path?name=value']
        ];
    }

    /**
     * @dataProvider getUris
     * @param string $uri
     * @param string $expected
     */
    public function testIsTargetBasedOnUri($uri, $expected)
    {
        $request = new Request('GET', $uri);
        $this->assertSame($expected, $request->getRequestTarget());
    }

    public function getTargets()
    {
        return [
            ['/path'], // origin-form
            ['/path?name=value'], // origin-form with query
            ['example.com'], // authority-form
            ['example.com:80'], // authority-form with port
            ['http://example.com/path'], // absolute-form
            ['http://example.com/path?name=value'], // absolute-form with query
            ['*'], // asterisk-form
        ];
    }

    /**
     * @dataProvider getTargets
     * @param string $target
     */
    public function testConstructWithTarget($target)
    {
        $request = new Request('GET', 'http://example.org/different/path', null, null, $target);
        $this->assertSame($target, $request->getRequestTarget());
    }

    /**
     * @dataProvider getTargets
     * @param string $target
     */
    public function testWithTarget($target)
    {
        $request = new Request('GET', 'http://example.org/different/path');
        $new = $request->withRequestTarget($target);
        $this->assertNotSame($request, $new);
        $this->assertSame($target, $new->getRequestTarget());
    }
}
