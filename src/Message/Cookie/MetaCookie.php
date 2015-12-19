<?php
namespace Icicle\Http\Message\Cookie;

interface MetaCookie extends Cookie
{
    /**
     * @return int Unix timestamp of expiration time.
     */
    public function getExpires(): int;

    /**
     * @return string Cookie path.
     */
    public function getPath(): string;

    /**
     * @return string Cookie domain.
     */
    public function getDomain(): string;

    /**
     * @return bool True if the cookie should be sent over HTTPS only.
     */
    public function isSecure(): bool;

    /**
     * @return bool True if the cookie should be available to HTTP requests only.
     */
    public function isHttpOnly(): bool;
}
