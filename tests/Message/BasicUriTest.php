<?php
namespace Icicle\Tests\Http\Message;

use Icicle\Http\Message\BasicUri;
use Icicle\Tests\Http\TestCase;

class BasicUriTest extends TestCase
{
    /**
     * @return array
     */
    public function getUris()
    {
        return [
            [
                'https://username:password@example.com:8443/async/http?foo=value1&bar=value2#fragment-value',
                'https',
                'username',
                'password',
                'example.com',
                8443,
                '/async/http',
                ['bar' => ['value2'], 'foo' => ['value1']],
                'fragment-value',
            ],
            [
                'http://example.com/path',
                'http',
                '',
                '',
                'example.com',
                80,
                '/path',
                [],
                '',
            ],
            [
                '//example.com',
                '',
                '',
                '',
                'example.com',
                0,
                '',
                [],
                '',
            ],
            [
                'example.org:443',
                '',
                '',
                '',
                'example.org',
                443,
                '',
                [],
                '',
            ],
            [
                '//example.com/path/to/resource',
                '',
                '',
                '',
                'example.com',
                0,
                '/path/to/resource',
                [],
                '',
            ],
            [
                '/path/without/host?name=value#fragment-value',
                '',
                '',
                '',
                '',
                0,
                '/path/without/host',
                ['name' => ['value']],
                'fragment-value',
            ],
            [
                'http://username@example.org/',
                'http',
                'username',
                '',
                'example.org',
                80,
                '/',
                [],
                '',
            ],
            [
                '',
                '',
                '',
                '',
                '',
                0,
                '',
                [],
                '',
            ],
        ];
    }

    /**
     * @dataProvider getUris
     *
     * @param string $uri
     * @param string $scheme
     * @param string $user
     * @param string $host
     * @param int|null $port
     * @param string $path
     * @param string $query
     * @param string $fragment
     */
    public function testConstructor($uri, $scheme, $user, $password, $host, $port, $path, $query, $fragment)
    {
        $uri = new BasicUri($uri);
        $this->assertSame($scheme, $uri->getScheme());
        $this->assertSame($user, $uri->getUser());
        $this->assertSame($password, $uri->getPassword());
        $this->assertSame($host, $uri->getHost());
        $this->assertSame($port, $uri->getPort());
        $this->assertSame($path, $uri->getPath());
        $this->assertSame($query, $uri->getQueryValues());
        $this->assertSame($fragment, $uri->getFragment());
    }

    /**
     * @depends testConstructor
     */
    public function testToString()
    {
        $uri = 'https://username:password@example.com:8443/async/http?name=value#fragment';
        $this->assertSame($uri, (string) new BasicUri($uri));
    }

    /**
     * @depends testConstructor
     */
    public function testWithScheme()
    {
        $uri = new BasicUri('https://username:password@example.com/async/http?name=value#fragment');

        $new = $uri->withScheme('http');

        $this->assertNotSame($uri, $new);
        $this->assertSame('http', $new->getScheme());
        $this->assertSame('http://username:password@example.com/async/http?name=value#fragment', (string) $new);

        $new = $uri->withScheme(null);

        $this->assertSame('', $new->getScheme());
        $this->assertSame('username:password@example.com/async/http?name=value#fragment', (string) $new);
    }

    /**
     * @depends testConstructor
     * @expectedException \Icicle\Http\Exception\InvalidValueException
     */
    public function testWithInvalidScheme()
    {
        new BasicUri('ftp://example.com/path');
    }

    /**
     * @return array
     */
    public function getValidPorts()
    {
        return [
            ['http://example.com', 80, 80],
            ['https://example.com', 443, 443],
            ['https://example.com', 8080, 8080],
            ['http://example.com:8080', 80, 80],
            ['https://example.com:8080', 80, 80],
            ['https://example.com', 8443, 8443],
            ['http://example.com:8080', null, 80],
            ['https://example.com:8443', null, 443],
            ['https://example.com', '8080', 8080],
        ];
    }

    /**
     * @depends testConstructor
     * @dataProvider getValidPorts
     *
     * @param string $uri
     * @param int|string|null $port
     * @param int $expected
     */
    public function testWithPort($uri, $port, $expected)
    {
        $uri = new BasicUri($uri);

        $new = $uri->withPort($port);

        $this->assertNotSame($uri, $new);
        $this->assertSame($expected, $new->getPort());
    }

    /**
     * @return array
     */
    public function getInvalidPorts()
    {
        return [
            [-1],
            [0xfffff],
        ];
    }

    /**
     * @depends testConstructor
     * @dataProvider getInvalidPorts
     * @expectedException \Icicle\Http\Exception\InvalidValueException
     *
     * @param int|string|null $port
     */
    public function testWithInvalidPort($port)
    {
        $uri = new BasicUri('http://example.com');
        $uri->withPort($port);
    }

    /**
     * @depends testConstructor
     */
    public function testWithUser()
    {
        $uri = new BasicUri('https://example.com:8443');

        $new = $uri->withUser('username', 'password');

        $this->assertNotSame($uri, $new);
        $this->assertSame('username', $new->getUser());
        $this->assertSame('password', $new->getPassword());
        $this->assertSame('https://username:password@example.com:8443', (string) $new);

        $new = $uri->withUser('username');

        $this->assertNotSame($uri, $new);
        $this->assertSame('username', $new->getUser());
        $this->assertSame('https://username@example.com:8443', (string) $new);

        $new = $uri->withUser('user name', 'påsswørd');

        $this->assertNotSame($uri, $new);
        $this->assertSame('user name', $new->getUser());
        $this->assertSame('påsswørd', $new->getPassword());
        $this->assertSame('https://user%20name:p%C3%A5ssw%C3%B8rd@example.com:8443', (string) $new);
    }

    /**
     * @depends testConstructor
     */
    public function testWithHost()
    {
        $uri = new BasicUri('https://username:password@example.com:443/path');

        $new = $uri->withHost('example.net');

        $this->assertNotSame($uri, $new);
        $this->assertSame('example.net', $new->getHost());
        $this->assertSame('https://username:password@example.net/path', (string) $new);

        $new = $uri->withHost(null);

        $this->assertNotSame($uri, $new);
        $this->assertSame('', $new->getHost());
        $this->assertSame('/path', (string) $new);
    }

    /**
     * @return array
     */
    public function getAuthorities()
    {
        return [
            ['https://example.com:443', '', '', 'example.com', 443, 'https://example.com'],
            ['http://example.com:8080', '', '', 'example.com', 8080, 'http://example.com:8080'],
            ['http://username@example.com:80', 'username', '', 'example.com', 80, 'http://username@example.com'],
            ['https://username:password@example.com:8443', 'username', 'password', 'example.com', 8443, 'https://username:password@example.com:8443'],
            ['https://username:password@example.com', 'username', 'password', 'example.com', 443, 'https://username:password@example.com'],
            ['/no/authority', '', '', '', 0, '/no/authority'],
            ['example.com:80', '', '', 'example.com', 80, 'example.com:80'],
            ['http://usérnäme:passwörd@example.com', 'usérnäme', 'passwörd', 'example.com', 80, 'http://us%C3%A9rn%C3%A4me:passw%C3%B6rd@example.com'],
        ];
    }

    /**
     * @dataProvider getAuthorities
     *
     * @param string $uri
     * @param string $user
     * @param string $password
     * @param string $host
     * @param int $port
     */
    public function testAuthority($uri, $user, $password, $host, $port, $encoded)
    {
        $uri = new BasicUri($uri);
        $this->assertSame($user, $uri->getUser());
        $this->assertSame($password, $uri->getPassword());
        $this->assertSame($host, $uri->getHost());
        $this->assertSame($port, $uri->getPort());
        $this->assertSame($encoded, (string) $uri);
    }

    /**
     * @depends testConstructor
     */
    public function testWithQuery()
    {
        $uri = new BasicUri('http://example.com/path?foo=bar');

        $new = $uri->withQuery('?key2=value2&key1=value1');

        $this->assertNotSame($uri, $new);
        $this->assertSame('value1', $new->getQueryValue('key1'));
        $this->assertSame('value2', $new->getQueryValue('key2'));
        $this->assertSame(['key1' => ['value1'], 'key2' => ['value2']], $new->getQueryValues());
        $this->assertSame('http://example.com/path?key1=value1&key2=value2', (string) $new);

        $new = $uri->withQuery('test1&test2=value');

        $this->assertNotSame($uri, $new);
        $this->assertSame('', $new->getQueryValue('test1'));
        $this->assertSame('value', $new->getQueryValue('test2'));
        $this->assertSame(['test1' => [''], 'test2' => ['value']], $new->getQueryValues());
        $this->assertSame('http://example.com/path?test1&test2=value', (string) $new);

        $new = $uri->withQuery('test[]=value1&test[]=value2');

        $this->assertNotSame($uri, $new);
        $this->assertSame('value1', $new->getQueryValue('test[]'));
        $this->assertSame(['value1', 'value2'], $new->getQueryValueAsArray('test[]'));
        $this->assertSame('http://example.com/path?test[]=value1&test[]=value2', (string) $new);
    }

    /**
     * @depends testConstructor
     */
    public function testWithQueryValue()
    {
        $uri = new BasicUri('http://example.com/path?foo=bar');

        $new = $uri->withQueryValue('name', 'valüe');

        $this->assertNotSame($uri, $new);
        $this->assertSame(['foo' => ['bar'], 'name' => ['valüe']], $new->getQueryValues());
        $this->assertSame('http://example.com/path?foo=bar&name=val%C3%BCe', (string) $new);

        $new = $uri->withQueryValue('tést', null);

        $this->assertNotSame($uri, $new);
        $this->assertSame(['foo' => ['bar'], 'tést' => ['']], $new->getQueryValues());
        $this->assertSame('http://example.com/path?foo=bar&t%C3%A9st', (string) $new);

        $new = $uri->withQueryValue('foo', 'foo=bar');

        $this->assertNotSame($uri, $new);
        $this->assertSame(['foo' => ['foo=bar']], $new->getQueryValues());
        $this->assertSame('http://example.com/path?foo=foo=bar', (string) $new);

        $new = $uri->withQueryValue('foo', ['value1', 'value2']);

        $this->assertNotSame($uri, $new);
        $this->assertSame(['value1', 'value2'], $new->getQueryValueAsArray('foo'));
        $this->assertSame(['foo' => ['value1', 'value2']], $new->getQueryValues());
        $this->assertSame('http://example.com/path?foo=value1&foo=value2', (string) $new);

        $new = $uri->withQueryValue('plus', '2+2=4');

        $this->assertNotSame($uri, $new);
        $this->assertSame(['2+2=4'], $new->getQueryValueAsArray('plus'));
        $this->assertSame('http://example.com/path?foo=bar&plus=2%2B2=4', (string) $new);
    }

    /**
     * @depends testConstructor
     */
    public function testWithoutQueryValue()
    {
        $uri = new BasicUri('http://example.com/path?key1=value1&key2=value2&key3');

        $new = $uri->withoutQueryValue('key2');

        $this->assertNotSame($uri, $new);
        $this->assertTrue($new->hasQueryValue('key1'));
        $this->assertFalse($new->hasQueryValue('key2'));
        $this->assertSame('', $new->getQueryValue('key2'));
        $this->assertSame(['key1' => ['value1'], 'key3' => ['']], $new->getQueryValues());
        $this->assertSame('http://example.com/path?key1=value1&key3', (string) $new);

        $new = $uri->withoutQueryValue('key1');

        $this->assertNotSame($uri, $new);
        $this->assertFalse($new->hasQueryValue('key1'));
        $this->assertTrue($new->hasQueryValue('key2'));
        $this->assertSame('', $new->getQueryValue('key1'));
        $this->assertSame(['key2' => ['value2'], 'key3' => ['']], $new->getQueryValues());
        $this->assertSame('http://example.com/path?key2=value2&key3', (string) $new);

        $new = $uri->withoutQueryValue('key1');
        $new = $new->withoutQueryValue('key2');
        $new = $new->withoutQueryValue('key3');

        $this->assertNotSame($uri, $new);
        $this->assertSame([], $new->getQueryValues());
        $this->assertSame('http://example.com/path', (string) $new);
    }

    /**
     * @return array
     */
    public function getPaths()
    {
        return [
            ['/', '/', '/'],
            ['', '', ''],
            [null, '', ''],
            ['path/to/file', '/path/to/file', '/path/to/file'],
            ['path with spaces', '/path with spaces', '/path%20with%20spaces'],
            ['påth/wïth/spécial/chârs', '/påth/wïth/spécial/chârs', '/p%C3%A5th/w%C3%AFth/sp%C3%A9cial/ch%C3%A2rs'],
            ['/p%C3%A5th/w%C3%AFth/enc%C3%B8ded/ch%C3%A2rs', '/påth/wïth/encøded/chârs', '/p%C3%A5th/w%C3%AFth/enc%C3%B8ded/ch%C3%A2rs'],
        ];
    }

    /**
     * @dataProvider getPaths
     *
     * @param string|null $path
     * @param string $expected
     */
    public function testWithPath($path, $expected, $encoded)
    {
        $uri = new BasicUri('http://example.com/original/path');

        $new = $uri->withPath($path);

        $this->assertNotSame($uri, $new);
        $this->assertSame($expected, $new->getPath());
        $this->assertSame(sprintf('http://example.com%s', $encoded), (string) $new);
    }

    /**
     * @return array
     */
    public function getFragments()
    {
        return [
            ['#', '', ''],
            ['', '', ''],
            [null, '', ''],
            ['new-fragment', 'new-fragment', '#new-fragment'],
            ['#with-hash', 'with-hash', '#with-hash'],
            ['wïth/spécial/chârs', 'wïth/spécial/chârs', '#w%C3%AFth/sp%C3%A9cial/ch%C3%A2rs'],
            ['#w%C3%AFth/enc%C3%B8ded/ch%C3%A2rs', 'wïth/encøded/chârs', '#w%C3%AFth/enc%C3%B8ded/ch%C3%A2rs'],
        ];
    }

    /**
     * @dataProvider getFragments
     *
     * @param string|null $path
     * @param string $expected
     */
    public function testWithFragment($fragment, $expected, $encoded)
    {
        $uri = new BasicUri('http://example.com/path#original-fragment');

        $new = $uri->withFragment($fragment);

        $this->assertNotSame($uri, $new);
        $this->assertSame($expected, $new->getFragment());
        $this->assertSame(sprintf('http://example.com/path%s', $encoded), (string) $new);
    }
}
