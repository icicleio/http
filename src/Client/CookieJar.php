<?php
namespace Icicle\Http\Client;

use Icicle\Http\Message\Cookie\MetaCookie;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;

class CookieJar
{
    /**
     * @var \Icicle\Http\Message\Cookie\MetaCookie[]
     */
    private $cookies = [];

    /**
     * @param \Icicle\Http\Message\Response $response
     *
     * @return \Icicle\Http\Client\CookieJar
     */
    public static function fromResponse(Response $response)
    {
        $jar = new self();
        $jar->addFromResponse($response);
        return $jar;
    }

    /**
     * @param \Icicle\Http\Message\Cookie\MetaCookie $cookie
     */
    public function insert(MetaCookie $cookie)
    {
        $this->cookies[$cookie->getName()] = $cookie;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists((string) $name, $this->cookies);
    }

    /**
     * @param string $name
     *
     * @return \Icicle\Http\Message\Cookie\MetaCookie|null
     */
    public function get($name)
    {
        $name = (string) $name;
        return array_key_exists($name, $this->cookies) ? $this->cookies[$name] : null;
    }

    /**
     * @param \Icicle\Http\Message\Response $response
     */
    public function addFromResponse(Response $response)
    {
        foreach ($response->getCookies() as $cookie) {
            $this->insert($cookie);
        }
    }

    /**
     * @param \Icicle\Http\Message\Request $request
     *
     * @return \Icicle\Http\Message\Request
     */
    public function addToRequest(Request $request)
    {
        foreach ($this->cookies as $cookie) {
            $request = $request->withCookie($cookie->getName(), $cookie->getValue());
        }

        return $request;
    }
}
