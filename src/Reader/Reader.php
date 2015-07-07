<?php
namespace Icicle\Http\Reader;

use Icicle\Http\Exception\MessageHeaderSizeException;
use Icicle\Http\Exception\MissingHostException;
use Icicle\Http\Exception\ParseException;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Http\Message\Uri;
use Icicle\Stream\ReadableStreamInterface;

class Reader implements ReaderInterface
{
    const DEFAULT_MAX_SIZE = 0x4000; // 16 kB

    private $maxHeaderSize = self::DEFAULT_MAX_SIZE;

    public function __construct(array $options = null)
    {
        $this->maxHeaderSize = isset($options['max_header_size'])
            ? (int) $options['max_header_size']
            : self::DEFAULT_MAX_SIZE;
    }

    /**
     * {@inheritdoc}
     */
    public function readResponse(ReadableStreamInterface $stream, $timeout = null)
    {
        $message = (yield $this->readMessage($stream, $timeout));

        $headers = $this->splitHeader($message);

        $start = array_shift($headers);

        if (!preg_match('/^HTTP\/(\d+(?:\.\d+)?) (\d{3})(?: (.+))?$/i', $start, $matches)) {
            throw new ParseException('Could not parse start line.');
        }

        $protocol = $matches[1];
        $code = (int) $matches[2];
        $reason = isset($matches[3]) ? $matches[3] : null;

        $headers = $this->parseHeaders($headers);

        yield new Response($code, $headers, $stream, $reason, $protocol);
    }

    /**
     * {@inheritdoc}
     */
    public function readRequest(ReadableStreamInterface $stream, $timeout = null)
    {
        $message = (yield $this->readMessage($stream, $timeout));

        $headers = $this->splitHeader($message);

        $start = array_shift($headers);

        if (!preg_match('/^([A-Z]+) (\S+) HTTP\/(\d+(?:\.\d+)?)$/i', $start, $matches)) {
            throw new ParseException('Could not parse start line.');
        }

        $method = $matches[1];
        $target = $matches[2];
        $protocol = $matches[3];

        $headers = $this->parseHeaders($headers);

        if ('/' === $target[0]) { // origin-form
            $uri = new Uri($this->filterHost($this->findHost($headers)) . $target);
            $target = null; // null $target since it was a path.
        } elseif ('*' === $target) { // asterisk-form
            $uri = new Uri($this->filterHost($this->findHost($headers)));
        } elseif (preg_match('/^https?:\/\//i', $target)) { // absolute-form
            $uri = new Uri($target);
        } else { // authority-form
            $uri = new Uri($this->filterHost($target));
        }

        yield new Request($method, $uri, $headers, $stream, $target, $protocol);
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Stream\ReadableStreamInterface $stream
     * @param float|int|null $timeout
     *
     * @return \Generator
     *
     * @resolve string
     *
     * @reject \Icicle\Http\Exception\MessageHeaderSizeException
     * @reject \Icicle\Socket\Exception\UnreadableException
     */
    protected function readMessage(ReadableStreamInterface $stream, $timeout = null)
    {
        $data = '';

        do {
            $data .= (yield $stream->read(null, "\n", $timeout));

            if (strlen($data) > $this->maxHeaderSize) {
                throw new MessageHeaderSizeException(
                    sprintf('Message header exceeded maximum size of %d bytes.', $this->maxHeaderSize)
                );
            }
        } while (substr($data, -4) !== "\r\n\r\n");

        yield substr($data, 0, -4);
    }

    /**
     * @param string[] $lines
     *
     * @return string[][]
     *
     * @throws \Icicle\Http\Exception\ParseException
     */
    protected function parseHeaders(array $lines)
    {
        $headers = [];

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);

            if (2 !== count($parts)) {
                throw new ParseException('Found header without colon.');
            }

            list($name, $value) = $parts;
            $value = trim($value);

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
     * @param string $header
     *
     * @return string[]
     */
    protected function splitHeader($header)
    {
        return explode("\r\n", $header);
    }

    /**
     * @param string $host
     *
     * @return string
     */
    protected function filterHost($host)
    {
        if (strrpos($host, ':', -1)) {
            return $host;
        }

        return '//' . $host;
    }

    /**
     * @param string[][] $headers
     *
     * @return string
     *
     * @throws \Icicle\Http\Exception\MissingHostException If no host header is find.
     */
    protected function findHost(array $headers)
    {
        foreach ($headers as $name => $values) {
            if (strtolower($name) === 'host') {
                return implode(',', $values);
            }
        }

        throw new MissingHostException('No host header in message.');
    }
}
