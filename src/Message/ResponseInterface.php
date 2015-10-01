<?php
namespace Icicle\Http\Message;

interface ResponseInterface extends MessageInterface
{
    /**
     * Returns the response status code.
     *
     * @return int
     */
    public function getStatusCode();

    /**
     * Returns the reason phrase describing the status code.
     *
     * @return string
     */
    public function getReasonPhrase();

    /**
     * @return \Icicle\Http\Message\Cookie\MetaCookieInterface[]
     */
    public function getCookies();

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasCookie($name);

    /**
     * @param string $name
     *
     * @return \Icicle\Http\Message\Cookie\MetaCookieInterface|null
     */
    public function getCookie($name);

    /**
     * Returns a new instance with the given status.
     *
     * @param int $code 3-digit status code.
     * @param string $reason Description of status code or null to use default reason associated with the status
     *     code given.
     *
     * @return self
     *
     * @throws \Icicle\Http\Exception\InvalidStatusException
     */
    public function withStatus($code, $reason = '');

    /**
     * @param string $name
     * @param string $value
     * @param int $expires
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     *
     * @return self
     */
    public function withCookie(
        $name,
        $value = '',
        $expires = 0,
        $path = '',
        $domain = '',
        $secure = false,
        $httpOnly = false
    );

    /**
     * @param string $name
     *
     * @return self
     */
    public function withoutCookie($name);
}
