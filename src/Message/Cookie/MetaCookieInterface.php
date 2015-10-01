<?php
namespace Icicle\Http\Message\Cookie;

interface MetaCookieInterface extends CookieInterface
{
    /**
     * @return int Unix timestamp of expiration time.
     */
    public function getExpires();

    /**
     * @return string Cookie path.
     */
    public function getPath();

    /**
     * @return string Cookie domain.
     */
    public function getDomain();

    /**
     * @return bool True if the cookie should be sent over HTTPS only.
     */
    public function isSecure();

    /**
     * @return bool True if the cookie should be available to HTTP requests only.
     */
    public function isHttpOnly();
}
