<?php
namespace Icicle\Http\Message;

if (!function_exists(__NAMESPACE__ . '\encode')) {
    /**
     * Escapes all reserved chars.
     *
     * @param string $string
     * @param bool $isPath
     *
     * @return string
     */
    function encode($string, $isPath = false)
    {
        if ($isPath) {
            $regex = '/(?:[^A-Za-z0-9_\-\.~\/%]+|%(?![A-Fa-f0-9]{2}))/';
        } else {
            $regex = '/(?:[^A-Za-z0-9_\-\.~!\$&\'\(\)\[\]\*\+,;=\/%]+|%(?![A-Fa-f0-9]{2}))/';
        }

        return preg_replace_callback($regex, function (array $matches) {
            return rawurlencode($matches[0]);
        }, $string);
    }

    /**
     * Decodes all URL encoded characters.
     *
     * @param string $string
     *
     * @return string
     */
    function decode($string)
    {
        return rawurldecode($string);
    }
}