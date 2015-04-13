<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\MessageException;
use Icicle\Http\Exception\ParseException;
use Icicle\Stream\ReadableStreamInterface;
use Icicle\Stream\Stream;

class Parser
{
    /**
     * @param   string $message
     * @param   \Icicle\Stream\ReadableStreamInterface|null $stream
     *
     * @return  \Icicle\Http\Message\Response
     *
     * @throws  \Icicle\Http\Exception\ParseException If parsing the message fails.
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If the message is invalid.
     */
    public function parseResponse($message, ReadableStreamInterface $stream = null)
    {
        list($startLine, $headers, $body) = $this->parseMessage($message);

        if (null === $stream) {
            $stream = new Stream();
            $stream->write($body);
        } elseif ('' !== $body) {
            throw new ParseException('Body portion in message when stream provided for body.');
        }

        if (!preg_match('/HTTP\/([0-9\.]+) (\d{3}) (.*)/i', $startLine, $matches)) {
            throw new ParseException('Could not parse start line.');
        }

        $protocol = $matches[1];
        $code = (int) $matches[2];
        $reason = $matches[3];

        $headers = $this->parseHeaders($headers);

        return new Response($code, $stream, $headers, $reason, $protocol);
    }

    /**
     * @param   string $message
     * @param   \Icicle\Stream\ReadableStreamInterface|null $stream
     *
     * @return  \Icicle\Http\Message\Request
     *
     * @throws  \Icicle\Http\Exception\ParseException If parsing the message fails.
     * @throws  \Icicle\Http\Exception\MessageException If no host header is found.
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If the message is invalid.
     */
    public function parseRequest($message, ReadableStreamInterface $stream = null)
    {
        list($startLine, $headers, $body) = $this->parseMessage($message);

        if (null === $stream) {
            $stream = new Stream();
            $stream->write($body);
        } elseif ('' !== $body) {
            throw new ParseException('Body portion in message when stream provided for body.');
        }

        if (!preg_match('/([A-Z]+) (\S+) HTTP\/([0-9\.]+)/i', $startLine, $matches)) {
            throw new ParseException('Could not parse start line.');
        }

        $method = $matches[1];
        $target = $matches[2];
        $protocol = $matches[3];

        $headers = $this->parseHeaders($headers);

        foreach ($headers as $name => $values) {
            if (strtolower($name) === 'host') {
                $host = $values[0];
                break;
            }
        }

        if (!isset($host)) {
            throw new MessageException('No Host header in message.');
        }

        if (substr($target, 0, 1) === '/') { // origin-form
            $uri = new Uri($host . $target);
            $target = null; // null $target since it was a path.
        } elseif ('*' === $target) {
            $uri = new Uri($host); // asterisk-form
        } else {
            $uri = new Uri($target); // absolute-form or authority-form
        }

        return new Request($method, $uri, $stream, $headers, $target, $protocol);
    }

    /**
     * @param   string $message
     *
     * @return  array
     */
    protected function parseMessage($message)
    {
        list($header, $body) = $this->splitMessage($message);

        if (empty($header)) {
            throw new ParseException('No header found in message.');
        }

        $lines = $this->splitHeader($header);

        if (empty($lines)) {
            throw new ParseException('No start line in message.');
        }

        $startLine = array_shift($lines);

        return [$startLine, $lines, $body];
    }

    /**
     * @param   string[] $lines
     *
     * @return  string[][]
     */
    protected function parseHeaders(array $lines)
    {
        $headers = [];

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            $name = trim($parts[0]);
            $value = isset($parts[1]) ? trim($parts[1]) : '';

            // No check for case as Message class will automatically combine similarly named headers.
            if (!isset($headers[$name])) {
                $headers[$name] = [$value];
            } else {
                $headers[$name][] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param   string $header
     *
     * @return  string[]
     */
    protected function splitHeader($header)
    {
        return preg_split("/\r?\n/", $header, null, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param   string $message
     *
     * @return  string[]
     */
    protected function splitMessage($message)
    {
        $parts = preg_split("/\r?\n\r?\n/", $message, 2);

        if (!isset($parts[1])) {
            $parts[1] = '';
        }

        return $parts;
    }
}
