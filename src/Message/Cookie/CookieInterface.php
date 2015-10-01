<?php
namespace Icicle\Http\Message\Cookie;

interface CookieInterface
{
    /**
     * @return string Cookie name.
     */
    public function getName();

    /**
     * @return string Cookie value.
     */
    public function getValue();

    /**
     * @return string Cookie formatted as an HTTP header.
     */
    public function toHeader();

    /**
     * @return string Cookie value.
     */
    public function __toString();
}
