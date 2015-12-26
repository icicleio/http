<?php
namespace Icicle\Http\Driver\Builder;

use Icicle\Coroutine\Coroutine;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Message\Message;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Http\Stream\ChunkedDecoder;
use Icicle\Http\Stream\ChunkedEncoder;
use Icicle\Http\Stream\ZlibDecoder;
use Icicle\Http\Stream\ZlibEncoder;
use Icicle\Socket\Socket;
use Icicle\Stream;
use Icicle\Stream\MemoryStream;
use Icicle\Stream\SeekableStream;

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
     * @var int Max body length for compressed streams.
     */
    private $maxBodyLength = self::DEFAULT_MAX_COMP_LENGTH;

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
            $this->compressTypes = $options['compress_types'];
        }

        $this->compressionLevel = isset($options['compression_level'])
            ? $options['compression_level']
            : ZlibEncoder::DEFAULT_LEVEL;

        $this->hwm = isset($options['hwm']) ? (int) $options['hwm'] : self::DEFAULT_STREAM_HWM;

        $this->maxBodyLength = isset($options['max_length']) ? $options['max_length'] : self::DEFAULT_MAX_COMP_LENGTH;

        $this->compressionEnabled = !isset($options['disable_compression']) ? extension_loaded('zlib') : false;

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
        Socket $socket,
        Response $response,
        Request $request = null,
        float $timeout = 0,
        bool $allowPersistent = false
    ) {
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

        return $this->buildOutgoingStream($socket, $response, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildOutgoingRequest(
        Socket $socket,
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

        return $this->buildOutgoingStream($socket, $request, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildIncomingRequest(Socket $socket, Request $request, float $timeout = 0): \Generator
    {
        if ($request->getMethod() === 'POST' || $request->getMethod() === 'PUT') {
            return $this->buildIncomingStream($socket, $request, $timeout);
        }

        $stream = new MemoryStream();
        yield from $stream->end(); // No body in other requests.

        return $request->withBody($stream);
    }

    /**
     * {@inheritdoc}
     */
    public function buildIncomingResponse(Socket $socket, Response $response, float $timeout = 0): \Generator
    {
        return $this->buildIncomingStream($socket, $response, $timeout);
    }

    /**
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Message $message
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Message
     *
     * @throws \Icicle\Http\Exception\MessageException
     */
    private function buildOutgoingStream(Socket $socket, Message $message, float $timeout = 0): \Generator
    {
        $body = $message->getBody();

        if ($body instanceof SeekableStream) {
            yield from $body->seek(0);
        }

        if (!$body->isReadable()) {
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
            $coroutine->done(null, function () use ($socket) {
                $socket->close();
            });

            $message = $message
                ->withBody($stream)
                ->withoutHeader('Content-Length');
        }

        if ($message->getProtocolVersion() === '1.1' && !$message->hasHeader('Content-Length')) {
            $stream = new ChunkedEncoder($this->hwm);
            $body = $message->getBody();

            $coroutine = new Coroutine(Stream\pipe($body, $stream, true, 0, null, $timeout));
            $coroutine->done(null, function () use ($socket) {
                $socket->close();
            });

            return $message
                ->withBody($stream)
                ->withHeader('Transfer-Encoding', 'chunked');
        }

        return $message;
    }

    /**
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Message $message
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Message
     *
     * @throws \Icicle\Http\Exception\MessageException
     */
    private function buildIncomingStream(Socket $socket, Message $message, float $timeout = 0): \Generator
    {
        $stream = $message->getBody();

        if ($stream instanceof SeekableStream) {
            yield from $stream->seek(0);
        }

        if (!$stream->isReadable()) {
            return $message;
        }

        if (strtolower($message->getHeader('Transfer-Encoding') === 'chunked')) {
            $stream = new ChunkedDecoder($this->hwm);
            $body = $message->getBody();

            $coroutine = new Coroutine(Stream\pipe($body, $stream, true, 0, null, $timeout));
            $coroutine->done(null, function () use ($socket) {
                $socket->close();
            });

            $message = $message->withBody($stream);
        } elseif ($message->hasHeader('Content-Length')) {
            $length = (int) $message->getHeader('Content-Length');
            if (0 > $length) {
                throw new MessageException(Response::BAD_REQUEST, 'Content-Length header invalid.');
            }
            $stream = new MemoryStream($this->hwm);
            $body = $message->getBody();

            $coroutine = new Coroutine(Stream\pipe($body, $stream, true, $length, null, $timeout));
            $coroutine->done(null, function () use ($socket) {
                $socket->close();
            });

            $message = $message->withBody($stream);
        } elseif (
            !$message instanceof Response // Response may have no length on incoming stream.
            && strtolower($message->getHeader('Connection')) !== 'close'
        ) {
            throw new MessageException(Response::LENGTH_REQUIRED, 'Content-Length header required.');
        }

        $contentEncoding = strtolower($message->getHeader('Content-Encoding'));

        switch ($contentEncoding) {
            case 'deflate':
            case 'gzip':
                $stream = new ZlibDecoder($this->hwm, $this->maxBodyLength);

                $coroutine = new Coroutine(Stream\pipe($message->getBody(), $stream, true, 0, null, $timeout));
                $coroutine->done(null, function () use ($socket) {
                    $socket->close();
                });

                return $message->withBody($stream);

            case '':
                return $message;

            default:
                throw new MessageException(
                    Response::BAD_REQUEST,
                    sprintf('Unsupported content encoding received: %s', $contentEncoding)
                );
        }
    }
}
