<?php
namespace Icicle\Http\Exception;

use Icicle\Exception\InvalidArgumentError;

class MessageException extends \Exception implements Exception
{
    public function __construct($code, $message, \Exception $previous = null)
    {
        if ($code < 400 || $code > 599) {
            throw new InvalidArgumentError('Invalid response code.');
        }

        parent::__construct($message, $code, $previous);
    }

    public function getResponseCode()
    {
        return $this->getCode();
    }
}
