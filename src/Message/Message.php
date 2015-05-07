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
            $this->setHeaders($headers);
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
        $new->protocol = $this->filterProtocolVersion($version);
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
     */
    protected function setHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $normalized = strtolower($name);
            $value = $this->filterHeader($value);

            if (array_key_exists($normalized, $this->headerNameMap)) {
                $name = $this->headerNameMap[$normalized];
                $this->headers[$name] = array_merge($this->headers[$name], $value);
            } else {
                $this->headerNameMap[$normalized] = $name;
                $this->headers[$name] = $value;
            }
        }
    }

    /**
     * Sets the named header to the given value.
     *
     * @param   string $name
     * @param   string|string[] $value
     *
     * @return  $this
     */
    protected function setHeader($name, $value)
    {
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
     */
    protected function addHeader($name, $value)
    {
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
     */
    protected function filterProtocolVersion($protocol)
    {
        if (!preg_match('/^\d+(?:\.\d+)?$/', $protocol)) {
            throw new InvalidArgumentException('Invalid format for protocol version.');
        }

        return $protocol;
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
        if (is_numeric($value) || is_null($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Header values must be strings or an array of strings.');
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