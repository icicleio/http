<?php
namespace Icicle\Http\Message;

interface Request extends Message
{
    /**
     * Same as Message::getHeaders(), except the Host header will always be set based on the URI.
     *
     * @return string[][]
     */
    public function getHeaders(): array;

    /**
     * Same as Message::getHeader(), except if the Host header is request and previously unset, the value
     * will be determined from the URI.
     *
     * @param string $name
     *
     * @return string[]
     */
    public function getHeader(string $name): array;

    /**
     * Same as Message::getHeaderLine(), except if the Host header is request and previously unset, the value
     * will be determined from the URI.
     *
     * @param string $name
     *
     * @return string
     */
    public function getHeaderLine(string $name): string;

    /**
     * Returns the target of the request. Unless explicitly set, this will usually be the path and query portion
     * of the URI.
     *
     * @return string
     */
    public function getRequestTarget(): string;

    /**
     * Returns the request method.
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Returns the request URI.
     *
     * @return \Icicle\Http\Message\Uri
     */
    public function getUri(): Uri;

    /**
     * @return \Icicle\Http\Message\Cookie\Cookie[]
     */
    public function getCookies(): array;

    /**
     * @param string $name
     *
     * @return \Icicle\Http\Message\Cookie\Cookie|null
     */
    public function getCookie(string $name);

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasCookie(string $name): bool;

    /**
     * Returns a new instance with the given request target.
     *
     * @param string $target
     *
     * @return self
     *
     * @throws \Icicle\Http\Exception\InvalidValueException If the target contains whitespace.
     */
    public function withRequestTarget(string $target): Request;

    /**
     * Returns a new instance with the given request method.
     *
     * @param string $method
     *
     * @return self
     *
     * @throws \Icicle\Http\Exception\InvalidValueException If the given method is invalid.
     */
    public function withMethod(string $method): Request;

    /**
     * Returns a new instance with the given URI.
     *
     * @param \Icicle\Http\Message\Uri $uri
     *
     * @return self
     */
    public function withUri($uri): Request;

    /**
     * @param string $name
     * @param string $value
     *
     * @return self
     */
    public function withCookie(string $name, $value): Request;

    /**
     * @param string $name
     *
     * @return self
     */
    public function withoutCookie(string $name): Request;
}
