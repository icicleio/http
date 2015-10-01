<?php
namespace Icicle\Http\Message\Cookie;

use Icicle\Http\Exception\InvalidValueException;

class Cookie implements CookieInterface
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
            throw new InvalidValueException('Invalid cookie header value.');
        }

        list($name, $value) = $parts;

        return new self($name, $value);
    }

    public function __construct($name, $value = '')
    {
        $this->name = (string) $name;
        $this->value = (string) $value;
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
        return $this->name . '=' . $this->value;
    }

}
