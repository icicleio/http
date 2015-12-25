<?php
namespace Icicle\Http\Message;

use Icicle\Stream\ReadableStream;

/**
 * HTTP message interface based on PSR-7, modified to use coroutine-based streams.
 */
interface Message
{
    /**
     * @return string
     */
    public function getProtocolVersion();

    /**
     * Returns the message headers as a string-indexed array of arrays of strings or an empty array if no headers
     * have been set.
     *
     * @return string[][]
     */
    public function getHeaders();

    /**
     * Determines if the message has the given header.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasHeader($name);

    /**
     * Returns the array of values for the given header or an empty array if the header does not exist.
     *
     * @param string $name
     *
     * @return string[]
     */
    public function getHeaderAsArray($name);

    /**
     * Returns the value of the given header. If multiple headers were present for the named header, only the first
     * header value will be returned. Use getHeaderAsArray() to return an array of all values for the particular header.
     * Returns an empty string if the header does not exist.
     *
     * @param string $name
     *
     * @return string
     */
    public function getHeader($name);

    /**
     * Returns the stream for the message body.
     *
     * @return \Icicle\Stream\ReadableStream
     */
    public function getBody();

    /**
     * Returns a new instance with the given protocol version.
     *
     * @param string $version
     *
     * @return self
     */
    public function withProtocolVersion($version);

    /**
     * Returns a new instance with the given header. $value may be a string or an array of strings.
     *
     * @param string $name
     * @param string|string[] $value
     *
     * @return self
     *
     * @throws \Icicle\Http\Exception\InvalidHeaderException If the header name or value is invalid.
     */
    public function withHeader($name, $value);

    /**
     * Returns a new instance with the given value added to the named header. If the header did not exist, the header
     * is created with the given value.
     *
     * @param string $name
     * @param string|string[] $value
     *
     * @return self
     *
     * @throws \Icicle\Http\Exception\InvalidHeaderException If the header name or value is invalid.
     */
    public function withAddedHeader($name, $value);

    /**
     * Returns a new instance without the given header.
     *
     * @param string $name
     *
     * @return self
     */
    public function withoutHeader($name);

    /**
     * Returns a new instance with the given stream for the message body.
     *
     * @param \Icicle\Stream\ReadableStream $stream
     *
     * @return self
     */
    public function withBody(ReadableStream $stream);
}
