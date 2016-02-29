<?php
namespace Icicle\Http\Message;

if (!function_exists(__NAMESPACE__ . '\encode')) {
    /**
     * Escapes URI value.
     *
     * @param string $value
     *
     * @return string
     */
    function encode(string $value): string
    {
        return preg_replace_callback(
            '/(?:[^A-Za-z0-9_\-\.~!\'\(\)\[\]\*]+|%(?![A-Fa-f0-9]{2}))/',
            function (array $matches) {
                return rawurlencode($matches[0]);
            },
            $value
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