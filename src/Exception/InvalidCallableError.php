<?php
namespace Icicle\Http\Exception;

class InvalidCallableError extends Error
{
    /**
     * @var callable
     */
    private $callable;

    /**
     * @param string $message
     * @param callable $callable
     */
    public function __construct($message, callable $callable)
    {
        parent::__construct($message);

        $this->callable = $callable;
    }

    /**
     * @return callable
     */
    public function getCallable()
    {
        return $this->callable;
    }
}
