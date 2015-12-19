<?php
namespace Icicle\Http\Exception;

class InvalidResultError extends \Error implements Error
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * @param string $message
     * @param mixed $value
     */
    public function __construct($message, $value)
    {
        parent::__construct($message);

        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
