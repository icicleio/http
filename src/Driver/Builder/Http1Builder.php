<?php
namespace Icicle\Http\Driver\Builder;

use Icicle\Coroutine\Coroutine;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Message\{Message, Request, Response};
use Icicle\Http\Stream\{ChunkedDecoder, ChunkedEncoder, ZlibDecoder, ZlibEncoder};
use Icicle\Stream;
use Icicle\Stream\{MemoryStream, SeekableStream};

class Http1Builder
{
    const DEFAULT_STREAM_HWM = 8192;
    const DEFAULT_MAX_COMP_LENGTH = 0x10000;
    const DEFAULT_KEEP_ALIVE_TIMEOUT = 15;
    const DEFAULT_KEEP_ALIVE_MAX = 100;

    private $compressTypes = [
        '/^text\/\S+/i',
        '/^application\/(?:json|javascript|(?:xhtml\+)?xml)/i',
        '/^image\/svg\+xml/i',
        '/^font\/(?:opentype|otf|ttf)/i'
    ];

    /**
     * @var bool
     */
    private $compressionEnabled = true;

    /**
     * @var int
     */
    private $compressionLevel = ZlibEncoder::DEFAULT_LEVEL;

    /**
     * @var int
     */
    private $hwm = self::DEFAULT_STREAM_HWM;

    /**
     * @var int
     */
    private $keepAliveTimeout = self::DEFAULT_KEEP_ALIVE_TIMEOUT;

    /**
     * @var int
     */
    private $keepAliveMax = self::DEFAULT_KEEP_ALIVE_MAX;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        if (isset($options['compress_types']) && is_array($options['compress_types'])) {
            $this->compressTypes = array_map(function ($type) {
                return (string) $type;
            }, $options['compress_types']);
        }

        $this->compressionEnabled = isset($options['disable_compression'])
            ? !$options['disable_compression']
            : extension_loaded('zlib');

        $this->compressionLevel = isset($options['compression_level'])
            ? (int) $options['compression_level']
            : ZlibEncoder::DEFAULT_LEVEL;

        $this->hwm = isset($options['hwm']) ? (int) $options['hwm'] : self::DEFAULT_STREAM_HWM;

        $this->maxBodyLength = isset($options['max_length']) ? $options['max_length'] : self::DEFAULT_MAX_COMP_LENGTH;

        $this->keepAliveTimeout = isset($options['keep_alive_timeout'])
            ? (int) $options['keep_alive_timeout']
            : self::DEFAULT_KEEP_ALIVE_TIMEOUT;

        $this->keepAliveMax = isset($options['keep_alive_max'])
            ? (int) $options['keep_alive_max']
            : self::DEFAULT_KEEP_ALIVE_MAX;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOutgoingResponse(
        Response $response,
        Request $request = null,
        float $timeout = 0,
        bool $allowPersistent = false
    ): \Generator {
        if ('upgrade' === strtolower($response->getHeader('Connection'))) {
            return $response;
        }

        if ($allowPersistent
            && null !== $request
            && 'keep-alive' === strtolower($request->getHeader('Connection'))
        ) {
            $response = $response
                ->withHeader('Connection', 'keep-alive')
                ->withHeader('Keep-Alive', sprintf('timeout=%d, max=%d', $this->keepAliveTimeout, $this->keepAliveMax));
        } else {
            $response = $response->withHeader('Connection', 'close');
        }

        $response = $response->withoutHeader('Content-Encoding');

        if ($this->compressionEnabled
            && null !== $request
            && $request->hasHeader('Accept-Encoding')
            && $response->hasHeader('Content-Type')
            && preg_match('/gzip|deflate/i', $request->getHeader('Accept-Encoding'), $matches)
        ) {
            $encoding = strtolower($matches[0]);
            $contentType = $response->getHeader('Content-Type');

            foreach ($this->compressTypes as $pattern) {
                if (preg_match($pattern, $contentType)) {
                    $response = $response->withHeader('Content-Encoding', $encoding);
                    break;
                }
            }
        }

        return $this->buildOutgoingStream($response, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildOutgoingRequest(
        Request $request,
        float $timeout = 0,
        bool $allowPersistent = false
    ): \Generator {
        if (!$request->hasHeader('Connection')) {
            $request = $request->withHeader('Connection', $allowPersistent ? 'keep-alive' : 'close');
        }

        if (!$request->hasHeader('Accept')) {
            $request = $request->withHeader('Accept', '*/*');
        }

        if ($this->compressionEnabled) {
            $request = $request->withHeader('Accept-Encoding', 'gzip, deflate');
        } else {
            $request = $request->withoutHeader('Accept-Encoding');
        }

        return $this->buildOutgoingStream($request, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildIncomingRequest(Request $request, float $timeout = 0): \Generator
    {
        return $this->buildIncomingStream($request, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildIncomingResponse(Response $response, float $timeout = 0): \Generator
    {
        return $this->buildIncomingStream($response, $timeout);
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Message $message
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Message
     *
     * @throws \Icicle\Http\Exception\MessageException
     */
    private function buildOutgoingStream(Message $message, float $timeout = 0): \Generator
    {
        $body = $message->getBody();

        if ($body instanceof SeekableStream && $body->isOpen()) {
            yield from $body->seek(0);
        }

        if (!$body->isReadable()) {
            if ($message instanceof Request) {
                switch ($message->getMethod()) {
                    case 'POST':
                    case 'PUT':
                        return $message->withHeader('Content-Length', 0);

                    default: // No content length header required on other methods.
                        return $message;
                }
            }

            return $message->withHeader('Content-Length', 0);
        }

        $contentEncoding = strtolower($message->getHeader('Content-Encoding'));

        if ('' !== $contentEncoding) {
            switch ($contentEncoding) {
                case 'deflate':
                    $stream = new ZlibEncoder(ZlibEncoder::DEFLATE, $this->compressionLevel, $this->hwm);
                    break;

                case 'gzip':
                    $stream = new ZlibEncoder(ZlibEncoder::GZIP, $this->compressionLevel, $this->hwm);
                    break;

                default:
                    throw new MessageException(
                        Response::BAD_REQUEST,
                        sprintf('Unsupported content encoding set: %s', $contentEncoding)
                    );
            }

            $coroutine = new Coroutine(Stream\pipe($body, $stream, true, 0, null, $timeout));
            $coroutine->done(null, [$stream, 'close']);

            $message = $message
                ->withBody($stream)
                ->withoutHeader('Content-Length');
        }

        if ($message->getProtocolVersion() === '1.1' && !$message->hasHeader('Content-Length')) {
            $stream = new ChunkedEncoder($this->hwm);

            $coroutine = new Coroutine(Stream\pipe($message->getBody(), $stream, true, 0, null, $timeout));
            $coroutine->done(null, [$stream, 'close']);

            return $message
                ->withBody($stream)
                ->withHeader('Transfer-Encoding', 'chunked');
        }

        return $message;
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Message $message
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Message
     *
     * @throws \Icicle\Http\Exception\MessageException
     */
    private function buildIncomingStream(Message $message, float $timeout = 0): \Generator
    {
        $body = $message->getBody();

        if ($body instanceof SeekableStream && $body->isOpen()) {
            yield from $body->seek(0);
        }

        if (!$body->isReadable()) {
            return $message;
        }

        if (strtolower($message->getHeader('Transfer-Encoding') === 'chunked')) {
            $stream = new ChunkedDecoder($this->hwm);

            $coroutine = new Coroutine(Stream\pipe($body, $stream, true, 0, null, $timeout));
            $coroutine->done(null, [$stream, 'close']);

            $message = $message->withBody($stream);
        } elseif ($message->hasHeader('Content-Length')) {
            $length = (int) $message->getHeader('Content-Length');
            if (0 > $length) {
                throw new MessageException(Response::BAD_REQUEST, 'Content-Length header invalid.');
            }
            $stream = new MemoryStream($this->hwm);

            if (0 === $length) {
                yield from $stream->end();
            } else {
                $coroutine = new Coroutine(Stream\pipe($body, $stream, true, $length, null, $timeout));
                $coroutine->done(null, [$stream, 'close']);
            }

            $message = $message->withBody($stream);
        } elseif ($message instanceof Request) {
            switch ($message->getMethod()) {
                case 'POST':
                case 'PUT': // Post and put messages must have content length or be transfer encoded.
                    throw new MessageException(Response::LENGTH_REQUIRED, 'Content-Length header required.');

                default: // Assume 0 length body.
                    $stream = new MemoryStream();
                    yield from $stream->end(); // Creates empty request body.

                    return $message->withBody($stream);
            }
        } elseif (strtolower($message->getHeader('Connection')) !== 'close') {
            throw new MessageException(Response::LENGTH_REQUIRED, 'Content-Length header required.');
        }

        $contentEncoding = strtolower($message->getHeader('Content-Encoding'));

        switch ($contentEncoding) {
            case 'deflate':
                $stream = new ZlibDecoder(ZlibDecoder::DEFLATE, $this->hwm);
                break;

            case 'gzip':
                $stream = new ZlibDecoder(ZlibDecoder::GZIP, $this->hwm);
                break;

            case '': // No content encoding.
                return $message;

            default:
                throw new MessageException(
                    Response::BAD_REQUEST,
                    sprintf('Unsupported content encoding received: %s', $contentEncoding)
                );
        }

        $coroutine = new Coroutine(Stream\pipe($message->getBody(), $stream, true, 0, null, $timeout));
        $coroutine->done(null, [$stream, 'close']);

        return $message->withBody($stream);
    }
}
