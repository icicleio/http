<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\InvalidHeaderException;
use Icicle\Http\Exception\UnsupportedVersionException;
use Icicle\Stream\ReadableStreamInterface;
use Icicle\Stream\MemorySink;

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
     * @param string[][] $headers
     * @param \Icicle\Stream\ReadableStreamInterface|null $stream
     * @param string $protocol
     *
     * @throws \Icicle\Http\Exception\MessageException
     */
    public function __construct(array $headers = [], ReadableStreamInterface $stream = null, $protocol = '1.1')
    {
        if (!empty($headers)) {
            $this->addHeaders($headers);
        }

        $this->stream = $stream ?: new MemorySink();
        $this->protocol = $this->filterProtocolVersion($protocol);
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion()
    {
        return $this->protocol;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader($name)
    {
        return array_key_exists(strtolower($name), $this->headerNameMap);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getHeaderLine($name)
    {
        $value = $this->getHeader($name);

        return empty($value) ? '' : implode(',', $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody()
    {
        return $this->stream;
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion($version)
    {
        $new = clone $this;
        $new->protocol = $new->filterProtocolVersion($version);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader($name, $value)
    {
        $new = clone $this;
        $new->setHeader($name, $value);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader($name, $value)
    {
        $new = clone $this;
        $new->addHeader($name, $value);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader($name)
    {
        $new = clone $this;
        $new->removeHeader($name);
        return $new;
    }

    /**
     * {@inheritdoc}
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
     * @param string[] $headers
     */
    protected function setHeaders(array $headers)
    {
        $this->headerNameMap = [];
        $this->headers = [];

        $this->addHeaders($headers);
    }

    /**
     * Adds headers from the given array.
     *
     * @param string[] $headers
     */
    protected function addHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->addHeader($name, $value);
        }
    }

    /**
     * Sets the named header to the given value.
     *
     * @param string $name
     * @param string|string[] $value
     *
     * @throws \Icicle\Http\Exception\InvalidHeaderException If the header name or value is invalid.
     */
    protected function setHeader($name, $value)
    {
        if (!$this->isHeaderNameValid($name)) {
            throw new InvalidHeaderException('Header name is invalid.');
        }

        $normalized = strtolower($name);
        $value = $this->filterHeader($value);

        // Header may have been previously set with a different case. If so, remove that header.
        if (isset($this->headerNameMap[$normalized]) && $this->headerNameMap[$normalized] !== $name) {
            unset($this->headers[$this->headerNameMap[$normalized]]);
        }

        $this->headerNameMap[$normalized] = $name;
        $this->headers[$name] = $value;
    }

    /**
     * Adds the value to the named header, or creates the header with the given value if it did not exist.
     *
     * @param string $name
     * @param string|string[] $value
     *
     * @throws \Icicle\Http\Exception\InvalidHeaderException If the header name or value is invalid.
     */
    protected function addHeader($name, $value)
    {
        if (!$this->isHeaderNameValid($name)) {
            throw new InvalidHeaderException('Header name is invalid.');
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
    }

    /**
     * Removes the given header if it exists.
     *
     * @param string $name
     */
    protected function removeHeader($name)
    {
        $normalized = strtolower($name);

        if (array_key_exists($normalized, $this->headerNameMap)) {
            $name = $this->headerNameMap[$normalized];
            unset($this->headers[$name], $this->headerNameMap[$normalized]);
        }
    }

    /**
     * @param string $protocol
     *
     * @return string
     *
     * @throws \Icicle\Http\Exception\UnsupportedVersionException If the protocol is not valid.
     */
    private function filterProtocolVersion($protocol)
    {
        switch ($protocol) {
            case '1.1':
            case '1.0':
                return $protocol;

            default:
                throw new UnsupportedVersionException('Invalid protocol version.');
        }
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    private function isHeaderNameValid($name)
    {
        return (bool) preg_match('/^[A-Za-z0-9`~!#$%^&_|\'\-]+$/', $name);
    }

    /**
     * Converts a given header value to an integer-indexed array of strings.
     *
     * @param mixed|mixed[] $values
     *
     * @return string[]
     *
     * @throws \Icicle\Http\Exception\InvalidHeaderException If the given value cannot be converted to a string and
     *     is not an array of values that can be converted to strings.
     */
    private function filterHeader($values)
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $lines = [];

        foreach ($values as $value) {
            if (is_numeric($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $value = (string) $value;
            } elseif (!is_string($value)) {
                throw new InvalidHeaderException('Header values must be strings or an array of strings.');
            }

            if (preg_match("/[^\t\r\n\x20-\x7e\x80-\xfe]|\r\n/", $value)) {
                throw new InvalidHeaderException('Invalid character(s) in header value.');
            }

            $lines[] = $value;
        }

        return $lines;
    }
}