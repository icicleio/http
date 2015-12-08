<?php
namespace Icicle\Tests\Http\Message;

use Icicle\Http\Message\Cookie\MetaCookie;
use Icicle\Http\Message\BasicResponse;
use Icicle\Tests\Http\TestCase;

class BasicResponseTest extends TestCase
{
    /**
     * @return array Array of arrays of invalid status codes.
     */
    public function getInvalidStatusCodes()
    {
        return [
            [99], // Too Low
            [600], // Too High
            [null], // null
            [false], // boolean
            [3.14], // float
            [[404]], // array
            ['200+'], // string (non-numeric)
            [new \stdClass()], // object
        ];
    }

    /**
     * @return array Array of arrays of valid status codes and associated reasons.
     */
    public function getValidStatusCodes()
    {
        return [
            [200, 'OK'],
            [404, 'Not Found'],
            ['101', 'Switching Protocols'],
            ['500', 'Internal Server Error'],
        ];
    }

    /**
     * @dataProvider getInvalidStatusCodes
     * @expectedException \Icicle\Http\Exception\InvalidStatusException
     */
    public function testConstructWithInvalidStatus($code)
    {
        new BasicResponse($code);
    }

    /**
     * @dataProvider getValidStatusCodes
     */
    public function testConstructWithValidStatus($code, $reason)
    {
        $response = new BasicResponse($code);
        $this->assertSame((int) $code, $response->getStatusCode());
        $this->assertSame($reason, $response->getReasonPhrase());
    }

    /**
     * @dataProvider getValidStatusCodes
     */
    public function testConstructWithReason($code)
    {
        $reason = 'Custom Reason';

        $response = new BasicResponse($code, [], null, $reason);
        $this->assertSame((int) $code, $response->getStatusCode());
        $this->assertSame($reason, $response->getReasonPhrase());
    }

    /**
     * @dataProvider getValidStatusCodes
     */
    public function testWithStatus($code, $reason)
    {
        $response = new BasicResponse();
        $new = $response->withStatus($code);

        $this->assertNotSame($response, $new);
        $this->assertSame((int) $code, $new->getStatusCode());
        $this->assertSame($reason, $new->getReasonPhrase());
    }

    /**
     * @dataProvider getValidStatusCodes
     */
    public function testWithStatusWithReason($code)
    {
        $reason = 'Custom Reason';

        $response = new BasicResponse();
        $new = $response->withStatus($code, $reason);

        $this->assertNotSame($response, $new);
        $this->assertSame((int) $code, $new->getStatusCode());
        $this->assertSame($reason, $new->getReasonPhrase());
    }

    /**
     * @dataProvider getInvalidStatusCodes
     * @expectedException \Icicle\Http\Exception\InvalidStatusException
     */
    public function testWithStatusWithInvalidCode($code)
    {
        (new BasicResponse())->withStatus($code);
    }

    public function testCookieDecode()
    {
        $request = new BasicResponse(200, [
            'Set-Cookie' => ['name1 = value1; path=/; domain=example.com', 'name2 = value2; path=/test']
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
     * @return \Icicle\Http\Message\Response
     */
    public function testWithCookie()
    {
        $response = new BasicResponse();

        $time = 1443657600;

        $new = $response->withCookie('name', 'value', $time, '/', 'example.com', true, true);
        $this->assertNotSame($response, $new);

        $this->assertTrue($new->hasCookie('name'));
        $cookie = $new->getCookie('name');
        $this->assertInstanceOf(MetaCookie::class, $cookie);
        $this->assertSame('name', $cookie->getName());
        $this->assertSame('value', $cookie->getValue());
        $this->assertSame($time, $cookie->getExpires());
        $this->assertSame('/', $cookie->getPath());
        $this->assertSame('example.com', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('value', (string) $cookie);

        $this->assertSame($cookie, $new->getCookies()['name']);

        $this->assertTrue($new->hasHeader('Set-Cookie'));
        $this->assertSame([
            'name=value; Expires=Thu, 1 Oct 2015 0:00:00 GMT; Path=/; Domain=example.com; Secure; HttpOnly'
        ], $new->getHeader('Set-Cookie'));

        return $new;
    }

    /**
     * @depends testWithCookie
     * @param \Icicle\Http\Message\Response $response
     *
     * @return \Icicle\Http\Message\Response
     */
    public function testWithAnotherCookie($response)
    {
        $time = 1443657600;

        $new = $response->withCookie('key', 'cookie-value', $time, '/test', 'example.net', true, false);
        $this->assertNotSame($response, $new);

        $this->assertTrue($new->hasCookie('key'));
        $this->assertTrue($new->hasCookie('name'));
        $cookie = $new->getCookie('key');
        $this->assertInstanceOf(MetaCookie::class, $cookie);
        $this->assertSame('key', $cookie->getName());
        $this->assertSame('cookie-value', $cookie->getValue());
        $this->assertSame($time, $cookie->getExpires());
        $this->assertSame('/test', $cookie->getPath());
        $this->assertSame('example.net', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertSame('cookie-value', (string) $cookie);

        $this->assertSame($cookie, $new->getCookies()['key']);

        $this->assertTrue($new->hasHeader('Set-Cookie'));
        $this->assertEquals([
            'name=value; Expires=Thu, 1 Oct 2015 0:00:00 GMT; Path=/; Domain=example.com; Secure; HttpOnly',
            'key=cookie-value; Expires=Thu, 1 Oct 2015 0:00:00 GMT; Path=/test; Domain=example.net; Secure',
        ], $new->getHeader('Set-Cookie'));

        return $new;
    }

    /**
     * @depends testWithAnotherCookie
     * @param \Icicle\Http\Message\Response $response
     *
     * @return \Icicle\Http\Message\Response
     */
    public function testWithoutCookie($response)
    {
        $new = $response->withoutCookie('name');
        $this->assertNotSame($response, $new);

        $this->assertFalse($new->hasCookie('name'));
        $this->assertTrue($new->hasCookie('key'));

        $cookie = $new->getCookie('key');

        $this->assertSame($cookie, $new->getCookies()['key']);

        $this->assertTrue($new->hasHeader('Set-Cookie'));
        $this->assertEquals([
            'key=cookie-value; Expires=Thu, 1 Oct 2015 0:00:00 GMT; Path=/test; Domain=example.net; Secure',
        ], $new->getHeader('Set-Cookie'));

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
        $new = $response->withHeader('Set-Cookie', 'test=cookie-value; Path=/test; Domain=example.com');

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
        $new = $response->withAddedHeader('Set-Cookie', 'test2=cookie-value2; Expires=Fri, 15 May 2015 12:00:00 GMT');

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
     * @param \Icicle\Http\Message\Response $response
     *
     * @return \Icicle\Http\Message\Response
     */
    public function testWithoutCookieHeader($response)
    {
        $new = $response->withoutHeader('Set-Cookie');

        $this->assertEmpty($new->getCookies());
    }
}
