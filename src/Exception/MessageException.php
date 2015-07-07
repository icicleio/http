<?php
namespace Icicle\Http\Exception;

class MessageException extends Exception
{
    public function __construct($code, $message, \Exception $previous = null)
    {
        if ($code < 400 || $code > 599) {
            throw new Error('Invalid response code.');
        }

        parent::__construct($message, $code, $previous);
    }

    public function getResponseCode()
    {
        return $this->getCode();
    }
}
