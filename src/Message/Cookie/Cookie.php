<?php
namespace Icicle\Http\Message\Cookie;

interface Cookie
{
    /**
     * @return string Cookie name.
     */
    public function getName(): string;

    /**
     * @return string Cookie value.
     */
    public function getValue(): string;

    /**
     * @return string Cookie formatted as an HTTP header.
     */
    public function toHeader(): string;

    /**
     * @return string Cookie value.
     */
    public function __toString(): string;
}
