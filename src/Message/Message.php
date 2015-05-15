<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\InvalidArgumentException;
use Icicle\Stream\ReadableStreamInterface;
use Icicle\Stream\Sink;

abstract class Message implements MessageInterface
{
    /**
     * @var string
     */
    private $protocol = '1.1';

    /**
     * @var string[]
     */
    private $headerNameMap = [];

    /**
     * @var string[][]
     */
    private $headers = [];

    /**
     * @var \Icicle\Stream\ReadableStreamInterface
     */
    private $stream;

    /**
     * @param   \Icicle\Stream\ReadableStreamInterface|null $stream
     * @param   string[][]|null $headers
     * @param   string $protocol
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException
     */
    public function __construct(array $headers = null, ReadableStreamInterface $stream = null, $protocol = '1.1')
    {
        if (null !== $headers) {
            $this->addHeaders($headers);
        }

        $this->stream = $stream ?: new Sink();
        $this->protocol = $this->filterProtocolVersion($protocol);
    }

    /**
     * @inheritdoc
     */
    public function getProtocolVersion()
    {
        return $this->protocol;
    }

    /**
     * @inheritdoc
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @inheritdoc
     */
    public function hasHeader($name)
    {
        return array_key_exists(strtolower($name), $this->headerNameMap);
    }

    /**
     * @inheritdoc
     */
    public function getHeader($name)
    {
        $name = strtolower($name);

        if (!array_key_exists($name, $this->headerNameMap)) {
            return [];
        }

        $name = $this->headerNameMap[$name];

        return $this->headers[$name];
    }

    /**
     * @inheritdoc
     */
    public function getHeaderLine($name)
    {
        $value = $this->getHeader($name);

        return empty($value) ? '' : implode(',', $value);
    }

    /**
     * @inheritdoc
     */
    public function getBody()
    {
        return $this->stream;
    }

    /**
     * @inheritdoc
     */
    public function withProtocolVersion($version)
    {
        $new = clone $this;
        $new->protocol = $new->filterProtocolVersion($version);
        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withHeader($name, $value)
    {
        $new = clone $this;
        return $new->setHeader($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function withAddedHeader($name, $value)
    {
        $new = clone $this;
        return $new->addHeader($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function withoutHeader($name)
    {
        $new = clone $this;
        return $new->removeHeader($name);
    }

    /**
     * @inheritdoc
     */
    public function withBody(ReadableStreamInterface $stream)
    {
        $new = clone $this;
        $new->stream = $stream;
        return $new;
    }

    /**
     * Sets the headers from the given array.
     *
     * @param   string[] $headers
     *
     * @return  $this
     */
    protected function setHeaders(array $headers)
    {
        $this->headerNameMap = [];
        $this->headers = [];

        return $this->addHeaders($headers);
    }

    /**
     * Adds headers from the given array.
     *
     * @param   string[] $headers
     *
     * @return  $this
     */
    protected function addHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->addHeader($name, $value);
        }

        return $this;
    }

    /**
     * Sets the named header to the given value.
     *
     * @param   string $name
     * @param   string|string[] $value
     *
     * @return  $this
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If the header name or value is invalid.
     */
    protected function setHeader($name, $value)
    {
        if (!$this->isHeaderNameValid($name)) {
            throw new InvalidArgumentException('Header name is invalid.');
        }

        $normalized = strtolower($name);
        $value = $this->filterHeader($value);

        // Header may have been previously set with a different case. If so, remove that header.
        if (isset($this->headerNameMap[$normalized]) && $this->headerNameMap[$normalized] !== $name) {
            unset($this->headers[$this->headerNameMap[$normalized]]);
        }

        $this->headerNameMap[$normalized] = $name;
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Adds the value to the named header, or creates the header with the given value if it did not exist.
     *
     * @param   string $name
     * @param   string|string[] $value
     *
     * @return  $this
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If the header name or value is invalid.
     */
    protected function addHeader($name, $value)
    {
        if (!$this->isHeaderNameValid($name)) {
            throw new InvalidArgumentException('Header name is invalid.');
        }

        $normalized = strtolower($name);
        $value = $this->filterHeader($value);

        if (array_key_exists($normalized, $this->headerNameMap)) {
            $name = $this->headerNameMap[$normalized]; // Use original case to add header value.
            $this->headers[$name] = array_merge($this->headers[$name], $value);
        } else {
            $this->headerNameMap[$normalized] = $name;
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Removes the given header if it exists.
     *
     * @param   string $name
     *
     * @return  $this
     */
    protected function removeHeader($name)
    {
        $normalized = strtolower($name);

        if (array_key_exists($normalized, $this->headerNameMap)) {
            $name = $this->headerNameMap[$normalized];
            unset($this->headers[$name], $this->headerNameMap[$normalized]);
        }

        return $this;
    }

    /**
     * @param   string $protocol
     *
     * @return  string
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If the protocol is not valid.
     */
    private function filterProtocolVersion($protocol)
    {
        switch ($protocol) {
            case '1.1':
            case '1.0':
                return $protocol;

            default:
                throw new InvalidArgumentException('Invalid protocol version.');
        }
    }

    /**
     * @param   string $name
     *
     * @return  bool
     */
    private function isHeaderNameValid($name)
    {
        return (bool) preg_match('/^[A-Za-z0-9`~!#$%^&_|\'\-]+$/', $name);
    }

    /**
     * Converts a given header value to an array of strings.
     *
     * @param   mixed|mixed[] $value
     *
     * @return  string[]
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If the given value cannot be converted to a string and
     *          is not an array of values that can be converted to strings.
     */
    private function filterHeader($value)
    {
        if (is_array($value)) {
            return $this->filterHeaderArray($value);
        }

        return [$this->filterHeaderValue($value)];
    }

    /**
     * @param   string|float|int|null $value
     *
     * @return  string
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If the given value cannot be converted to a string.
     */
    private function filterHeaderValue($value)
    {
        if (is_numeric($value) || (is_object($value) && method_exists($value, '__toString'))) {
            $value = (string) $value;
        } elseif (!is_string($value)) {
            throw new InvalidArgumentException('Header values must be strings or an array of strings.');
        }

        if (!preg_match("/^[\t\x20-\x7e\x80-\xfe]+$/", $value)) {
            throw new InvalidArgumentException('Invalid character in header value.');
        }

        return $value;
    }

    /**
     * @param   mixed[] $values
     *
     * @return  string[]
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If the given value is not an array of values that can
     *          be converted to strings.
     */
    private function filterHeaderArray(array $values)
    {
        $lines = [];

        foreach ($values as $value) {
            $lines[] = $this->filterHeaderValue($value);
        }

        return $lines;
    }
}