<?php
namespace Icicle\Http\Reader;

use Icicle\Http\Exception\MessageException;
use Icicle\Http\Exception\ParseException;
use Icicle\Http\Message\BasicRequest;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\BasicUri;
use Icicle\Http\Message\Response;
use Icicle\Stream\ReadableStream;

class Http1Reader
{
    const DEFAULT_MAX_SIZE = 0x4000; // 16 kB

    /**
     * @var int
     */
    private $maxHeaderSize = self::DEFAULT_MAX_SIZE;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        $this->maxHeaderSize = isset($options['max_header_size'])
            ? (int) $options['max_header_size']
            : self::DEFAULT_MAX_SIZE;
    }

    /**
     * {@inheritdoc}
     */
    public function readResponse(ReadableStream $stream, $timeout = 0)
    {
        $data = (yield $stream->read(0, "\n", $timeout));

        if (!preg_match("/^HTTP\/(\d+(?:\.\d+)?) (\d{3})(?: (.+))?\r\n$/i", $data, $matches)) {
            throw new ParseException('Could not parse start line.');
        }

        $protocol = $matches[1];
        $code = (int) $matches[2];
        $reason = isset($matches[3]) ? $matches[3] : '';

        $headers = (yield $this->readHeaders($stream, $timeout));

        yield new BasicResponse($code, $headers, $stream, $reason, $protocol);
    }

    /**
     * {@inheritdoc}
     */
    public function readRequest(ReadableStream $stream, $timeout = 0)
    {
        $data = (yield $stream->read(0, "\n", $timeout));

        if (!preg_match("/^([A-Z]+) (\S+) HTTP\/(\d+(?:\.\d+)?)\r\n$/i", $data, $matches)) {
            throw new ParseException('Could not parse start line.');
        }

        $method = $matches[1];
        $target = $matches[2];
        $protocol = $matches[3];

        $headers = (yield $this->readHeaders($stream, $timeout));

        if ('/' === $target[0]) { // origin-form
            $uri = new BasicUri($this->filterHost($this->findHost($headers)) . $target);
            $target = ''; // Empty request target since it was a path.
        } elseif ('*' === $target) { // asterisk-form
            $uri = new BasicUri($this->filterHost($this->findHost($headers)));
        } elseif (preg_match('/^https?:\/\//i', $target)) { // absolute-form
            $uri = new BasicUri($target);
        } else { // authority-form
            $uri = new BasicUri($this->filterHost($target));
        }

        yield new BasicRequest($method, $uri, $headers, $stream, $target, $protocol);
    }

    /**
     * @param ReadableStream $stream
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @throws \Icicle\Http\Exception\MessageException
     * @throws \Icicle\Http\Exception\ParseException
     */
    protected function readHeaders(ReadableStream $stream, $timeout = 0)
    {
        $size = 0;
        $headers = [];

        do {
            $data = (yield $stream->read(0, "\n", $timeout));

            if (substr($data, -2) !== "\r\n") {
                throw new ParseException('Invalid header line.');
            }

            $length = strlen($data);

            if ($length === 2) {
                yield $headers;
                return;
            }

            $size += $length;

            $parts = explode(':', $data, 2);

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
        } while ($size < $this->maxHeaderSize);

        throw new MessageException(
            Response::REQUEST_HEADER_TOO_LARGE,
            sprintf('Message header exceeded maximum size of %d bytes.', $this->maxHeaderSize)
        );
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
     * @throws \Icicle\Http\Exception\MessageException If no host header is find.
     */
    protected function findHost(array $headers)
    {
        foreach ($headers as $name => $values) {
            if (strtolower($name) === 'host') {
                return implode(',', $values);
            }
        }

        throw new MessageException(Response::BAD_REQUEST, 'No host header in message.');
    }
}
