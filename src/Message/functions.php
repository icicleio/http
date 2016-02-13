<?php
namespace Icicle\Http\Message;

if (!function_exists(__NAMESPACE__ . '\encodeValue')) {
    /**
     * Escapes URI value.
     *
     * @param string $value
     *
     * @return string
     */
    function encodeValue(string $value): string
    {
        return preg_replace_callback(
            '/(?:[^A-Za-z0-9_\-\.~!\$&\'\(\)\[\]\*\+,:;=\/%]+|%(?![A-Fa-f0-9]{2}))/',
            function (array $matches) {
                return rawurlencode($matches[0]);
            },
            $value
        );
    }

    /**
     * Escapes path.
     *
     * @param string $path
     *
     * @return string
     */
    function encodePath(string $path): string
    {
        return preg_replace_callback(
            '/(?:[^A-Za-z0-9_\-\.~\/:%]+|%(?![A-Fa-f0-9]{2}))/',
            function (array $matches) {
                return rawurlencode($matches[0]);
            },
            $path
        );
    }


    /**
     * Escapes header value.
     *
     * @param string $header
     *
     * @return string
     */
    function encodeHeader(string $header): string
    {
        return preg_replace_callback(
            '/(?:[^A-Za-z0-9_\-\.~!\$&\'\(\)\[\]\*\+,:;=\/% ]+|%(?![A-Fa-f0-9]{2}))/',
            function (array $matches) {
                return rawurlencode($matches[0]);
            },
            $header
        );
    }

    /**
     * Decodes all URL encoded characters.
     *
     * @param string $string
     *
     * @return string
     */
    function decode(string $string): string
    {
        return rawurldecode($string);
    }
}