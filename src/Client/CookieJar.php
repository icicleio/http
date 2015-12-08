<?php
namespace Icicle\Http\Client;

use Icicle\Http\Message\Cookie\MetaCookieInterface;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\ResponseInterface;

class CookieJar
{
    /**
     * @var \Icicle\Http\Message\Cookie\MetaCookieInterface[]
     */
    private $cookies = [];

    /**
     * @param \Icicle\Http\Message\ResponseInterface $response
     *
     * @return \Icicle\Http\Client\CookieJar
     */
    public static function fromResponse(ResponseInterface $response)
    {
        $jar = new self();
        $jar->addFromResponse($response);
        return $jar;
    }

    /**
     * @param \Icicle\Http\Message\Cookie\MetaCookieInterface $cookie
     */
    public function insert(MetaCookieInterface $cookie)
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
     * @return \Icicle\Http\Message\Cookie\MetaCookieInterface|null
     */
    public function get($name)
    {
        $name = (string) $name;
        return array_key_exists($name, $this->cookies) ? $this->cookies[$name] : null;
    }

    /**
     * @param \Icicle\Http\Message\ResponseInterface $response
     */
    public function addFromResponse(ResponseInterface $response)
    {
        foreach ($response->getCookies() as $cookie) {
            $this->insert($cookie);
        }
    }

    /**
     * @param \Icicle\Http\Message\RequestInterface $request
     *
     * @return \Icicle\Http\Message\RequestInterface
     */
    public function addToRequest(RequestInterface $request)
    {
        foreach ($this->cookies as $cookie) {
            $request = $request->withCookie($cookie->getName(), $cookie->getValue());
        }

        return $request;
    }
}
