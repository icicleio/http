<?php
namespace Icicle\Http\Message;

interface Request extends Message
{
    /**
     * Same as Message::getHeaders(), except the Host header will always be set based on the URI.
     *
     * @return string[][]
     */
    public function getHeaders();

    /**
     * Same as Message::getHeader(), except if the Host header is request and previously unset, the value
     * will be determined from the URI.
     *
     * @param string $name
     *
     * @return string[]
     */
    public function getHeader($name);

    /**
     * Same as Message::getHeaderLine(), except if the Host header is request and previously unset, the value
     * will be determined from the URI.
     *
     * @param string $name
     *
     * @return string
     */
    public function getHeaderLine($name);

    /**
     * Returns the target of the request. Unless explicitly set, this will usually be the path and query portion
     * of the URI.
     *
     * @return string
     */
    public function getRequestTarget();

    /**
     * Returns the request method.
     *
     * @return string
     */
    public function getMethod();

    /**
     * Returns the request URI.
     *
     * @return \Icicle\Http\Message\Uri
     */
    public function getUri();

    /**
     * @return \Icicle\Http\Message\Cookie\Cookie[]
     */
    public function getCookies();

    /**
     * @param string $name
     *
     * @return \Icicle\Http\Message\Cookie\Cookie
     */
    public function getCookie($name);

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasCookie($name);

    /**
     * Returns a new instance with the given request target.
     *
     * @param string|null $target
     *
     * @return self
     *
     * @throws \Icicle\Http\Exception\InvalidValueException If the target contains whitespace.
     */
    public function withRequestTarget($target = null);

    /**
     * Returns a new instance with the given request method.
     *
     * @param string $method
     *
     * @return self
     *
     * @throws \Icicle\Http\Exception\InvalidValueException If the given method is invalid.
     */
    public function withMethod($method);

    /**
     * Returns a new instance with the given URI.
     *
     * @param string|\Icicle\Http\Message\Uri $uri
     *
     * @return self
     */
    public function withUri($uri);

    /**
     * @param string $name
     * @param string $value
     *
     * @return self
     */
    public function withCookie($name, $value);

    /**
     * @param string $name
     *
     * @return self
     */
    public function withoutCookie($name);
}
