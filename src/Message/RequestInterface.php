<?php
namespace Icicle\Http\Message;

interface RequestInterface extends MessageInterface
{
    /**
     * Same as MessageInterface::getHeaders(), except the Host header will always be set based on the URI.
     *
     * @return string[][]
     */
    public function getHeaders();

    /**
     * Same as MessageInterface::getHeader(), except if the Host header is request and previously unset, the value
     * will be determined from the URI.
     *
     * @param string $name
     *
     * @return string[]
     */
    public function getHeader($name);

    /**
     * Same as MessageInterface::getHeaderLine(), except if the Host header is request and previously unset, the value
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
     * @return \Icicle\Http\Message\UriInterface
     */
    public function getUri();

    /**
     * Returns a new instance with the given request target.
     *
     * @param string $target
     *
     * @return static
     *
     * @throws \Icicle\Http\Exception\InvalidArgumentException If the target contains whitespace.
     */
    public function withRequestTarget($target);

    /**
     * Returns a new instance with the given request method.
     *
     * @param string $method
     *
     * @return static
     *
     * @throws \Icicle\Http\Exception\InvalidArgumentException If the given method is invalid.
     */
    public function withMethod($method);

    /**
     * Returns a new instance with the given URI.
     *
     * @param string|\Icicle\Http\Message\UriInterface $uri
     *
     * @return static
     */
    public function withUri($uri);
}
