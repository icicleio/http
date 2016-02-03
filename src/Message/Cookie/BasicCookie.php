<?php
namespace Icicle\Http\Message\Cookie;

use Icicle\Http\Exception\InvalidValueException;
use Icicle\Http\Message;

class BasicCookie implements Cookie
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $value;

    /**
     * @param string $string Valid Set-Cookie header line.
     *
     * @return self
     *
     * @throws \Icicle\Http\Exception\InvalidValueException Thrown if the string format is invalid.
     */
    public static function fromHeader($string)
    {
        $parts = array_map('trim', explode('=', $string, 2));

        if (2 !== count($parts)) {
            throw new InvalidValueException('Invalid cookie header format.');
        }

        list($name, $value) = $parts;

        return new self($name, $value);
    }

    public function __construct($name, $value = '')
    {
        $this->name = $this->filterValue($name);
        $this->value = $this->filterValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function toHeader()
    {
        return Message\encodeValue($this->name) . '=' . Message\encodeValue($this->value);
    }

    /**
     * @param string $value
     * @return string mixed
     *
     * @throws \Icicle\Http\Exception\InvalidValueException
     */
    protected function filterValue($value)
    {
        $value = (string) $value;

        if (preg_match("/[^\x21\x23-\x23\x2d-\x3a\x3c-\x5b\x5d-\x7e]/", $value)) {
            throw new InvalidValueException('Invalid cookie header value.');
        }

        return Message\decode($value);
    }
}
