<?php
namespace Icicle\Tests\Http\Message;

use Icicle\Http\Message\Cookie\Cookie;
use Icicle\Http\Message\BasicRequest;
use Icicle\Http\Message\BasicUri;
use Icicle\Tests\Http\TestCase;

class BasicRequestTest extends TestCase
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
        new BasicRequest($method);
    }

    /**
     * @dataProvider getValidMethods
     * @param string $method
     */
    public function testConstructWithValidMethod($method)
    {
        $request = new BasicRequest($method);
        $this->assertSame($method, $request->getMethod());
    }

    /**
     * @expectedException \Icicle\Http\Exception\InvalidMethodException
     * @dataProvider getInvalidMethods
     * @param string $method
     */
    public function testWithMethodThrowsExceptionWithInvalidMethod($method)
    {
        $request = new BasicRequest('GET');
        $request->withMethod($method);
    }

    /**
     * @dataProvider getValidMethods
     * @param string $method
     */
    public function testWithMethod($method)
    {
        $request = new BasicRequest('GET');
        $new = $request->withMethod($method);
        $this->assertNotSame($request, $new);
        $this->assertSame($method, $new->getMethod());
    }

    public function testUriWithoutHostDoesNotSetHostHeader()
    {
        $request = new BasicRequest('GET', new BasicUri('/path'));
        $this->assertFalse($request->hasHeader('Host'));
    }

    public function testUriWithHostSetsHostHeader()
    {
        $request = new BasicRequest('GET', new BasicUri('http://example.com:8080/path'));
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

        $request = new BasicRequest('GET', new BasicUri('http://example.com/path'), $headers);

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
        $original = new BasicUri('http://example.com');
        $substitute = new BasicUri('http://example.net');

        $request = new BasicRequest('GET', $original);
        $new = $request->withUri($substitute);
        $this->assertNotSame($request, $new);
        $this->assertSame($original, $request->getUri());
        $this->assertSame($substitute, $new->getUri());
    }

    public function testWithUriWithString()
    {
        $original = 'http://example.com';
        $substitute = 'http://example.net';

        $request = new BasicRequest('GET', $original);
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
        $request = new BasicRequest('GET', new BasicUri('http://example.com'));
        $new = $request->withUri(new BasicUri('http://example.net'));

        $this->assertTrue($new->hasHeader('Host'));
        $this->assertSame('example.net:80', $new->getHeaderLine('Host'));
    }

    /**
     * @expectedException \Icicle\Http\Exception\InvalidValueException
     */
    public function testConstructWithInvalidTarget()
    {
        new BasicRequest('GET', '', [], null, 'Invalid target');
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
        $request = new BasicRequest('GET', $uri);
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
        $request = new BasicRequest('GET', 'http://example.org/different/path', [], null, $target);
        $this->assertSame($target, $request->getRequestTarget());
    }

    /**
     * @dataProvider getTargets
     * @param string $target
     */
    public function testWithTarget($target)
    {
        $request = new BasicRequest('GET', 'http://example.org/different/path');
        $new = $request->withRequestTarget($target);
        $this->assertNotSame($request, $new);
        $this->assertSame($target, $new->getRequestTarget());
    }

    public function testCookieDecode()
    {
        $request = new BasicRequest('GET', 'http://example.org/different/path', [
            'Cookie' => 'name1 = value1; name2=value2'
        ]);

        $cookies = $request->getCookies();

        $this->assertInternalType('array', $cookies);
        $this->assertSame(2, count($cookies));
        $this->assertArrayHasKey('name1', $cookies);
        $this->assertArrayHasKey('name2', $cookies);

        $this->assertSame('name1', $cookies['name1']->getName());
        $this->assertSame('value1', $cookies['name1']->getValue());
        $this->assertSame('name2', $cookies['name2']->getName());
        $this->assertSame('value2', $cookies['name2']->getValue());
    }

    /**
     * @depends testCookieDecode
     *
     * @return \Icicle\Http\Message\Request
     */
    public function testWithCookie()
    {
        $request = new BasicRequest('GET', 'http://example.com');

        $new = $request->withCookie('name', 'value');
        $this->assertNotSame($request, $new);

        $this->assertTrue($new->hasCookie('name'));
        $cookie = $new->getCookie('name');
        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertSame('name', $cookie->getName());
        $this->assertSame('value', $cookie->getValue());
        $this->assertSame('value', (string) $cookie);

        $this->assertSame($cookie, $new->getCookies()['name']);

        $this->assertTrue($new->hasHeader('Cookie'));
        $this->assertSame(['name=value'], $new->getHeader('Cookie'));

        return $new;
    }

    /**
     * @depends testWithCookie
     * @param \Icicle\Http\Message\Request $request
     *
     * @return \Icicle\Http\Message\Request
     */
    public function testWithAnotherCookie($request)
    {
        $new = $request->withCookie('key', 'cookie-value');
        $this->assertNotSame($request, $new);

        $this->assertTrue($new->hasCookie('name'));
        $this->assertTrue($new->hasCookie('key'));
        $cookie = $new->getCookie('key');
        $this->assertSame('key', $cookie->getName());
        $this->assertSame('cookie-value', $cookie->getValue());

        $this->assertTrue($new->hasHeader('Cookie'));
        $this->assertEquals(['name=value', 'key=cookie-value'], explode('; ', $new->getHeader('Cookie')[0]));

        return $new;
    }

    /**
     * @depends testWithAnotherCookie
     * @param \Icicle\Http\Message\Request $request
     *
     * @return \Icicle\Http\Message\Request
     */
    public function testWithoutCookie($request)
    {
        $new = $request->withoutCookie('name');
        $this->assertNotSame($request, $new);
        $this->assertFalse($new->hasCookie('name'));
        $this->assertTrue($new->hasCookie('key'));

        $cookie = $new->getCookie('key');
        $this->assertSame($cookie, $new->getCookies()['key']);

        $this->assertTrue($new->hasHeader('Cookie'));
        $this->assertEquals(['key=cookie-value'], $new->getHeader('Cookie'));

        return $new;
    }

    /**
     * @depends testWithCookie
     * @param \Icicle\Http\Message\Response $response
     *
     * @return \Icicle\Http\Message\Response
     */
    public function testWithCookieHeader($response)
    {
        $new = $response->withHeader('Cookie', 'test=cookie-value');

        $this->assertTrue($new->hasCookie('test'));
        $cookie = $new->getCookie('test');
        $this->assertSame('test', $cookie->getName());
        $this->assertSame('cookie-value', $cookie->getValue());

        return $new;
    }

    /**
     * @depends testWithCookieHeader
     * @param \Icicle\Http\Message\Response $response
     *
     * @return \Icicle\Http\Message\Response
     */
    public function testWithAddedCookieHeader($response)
    {
        $new = $response->withAddedHeader('Cookie', 'test2=cookie-value2');

        $this->assertTrue($new->hasCookie('test'));
        $cookie = $new->getCookie('test');
        $this->assertSame('test', $cookie->getName());
        $this->assertSame('cookie-value', $cookie->getValue());

        $this->assertTrue($new->hasCookie('test2'));
        $cookie = $new->getCookie('test2');
        $this->assertSame('test2', $cookie->getName());
        $this->assertSame('cookie-value2', $cookie->getValue());

        return $new;
    }

    /**
     * @depends testWithoutCookie
     * @param \Icicle\Http\Message\Request $request
     *
     * @return \Icicle\Http\Message\Request
     */
    public function testWithoutCookieHeader($request)
    {
        $new = $request->withoutHeader('Cookie');

        $this->assertEmpty($new->getCookies());
    }
}
