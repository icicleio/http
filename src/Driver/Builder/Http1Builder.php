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
            $this->compressTypes = $options['compress_types'];
        }

        if (isset($options['hwm'])) {
            $this->hwm = (int) $options['hwm'];
        }

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
        $timeout = 0,
        $allowPersistent = false
    ) {
        if (null === $request) { // Fallback to 1.0 for responses due to message errors.
            $response = $response->withProtocolVersion('1.0');
        } elseif ($request->getProtocolVersion() !== $response->getProtocolVersion()) {
            $response = $response->withProtocolVersion($request->getProtocolVersion());
        }

        if (strtolower($response->getHeaderLine('Connection')) === 'upgrade') {
            yield $response;
            return;
        }

        if ($response->getProtocolVersion() === '1.1'
            && !$response->hasHeader('Connection')
            && $allowPersistent
            && strtolower($request->getHeaderLine('Connection')) === 'keep-alive'
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
            && preg_match('/gzip|deflate/i', $request->getHeaderLine('Accept-Encoding'), $matches)
        ) {
            $encoding = strtolower($matches[0]);
            $contentType = $response->getHeaderLine('Content-Type');

            foreach ($this->compressTypes as $pattern) {
                if (preg_match($pattern, $contentType)) {
                    $response = $response->withHeader('Content-Encoding', $encoding);
                    break;
                }
            }
        }

        yield $this->buildOutgoingStream($socket, $response, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildOutgoingRequest(Socket $socket, Request $request, $timeout = 0, $allowPersistent = false)
    {
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

        yield $this->buildOutgoingStream($socket, $request, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildIncomingRequest(Socket $socket, Request $request, $timeout = 0)
    {
        if ($request->getMethod() === 'POST' || $request->getMethod() === 'PUT') {
            yield $this->buildIncomingStream($socket, $request, $timeout);
            return;
        }

        $stream = new MemoryStream();
        yield $stream->end(); // No body in other requests.

        yield $request->withBody($stream);
    }

    /**
     * {@inheritdoc}
     */
    public function buildIncomingResponse(Socket $socket, Response $response, $timeout = 0)
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
    private function buildOutgoingStream(Socket $socket, Message $message, $timeout = 0)
    {
        $body = $message->getBody();

        if ($body instanceof SeekableStream) {
            yield $body->seek(0);
        }

        if (!$body->isReadable()) {
            yield $message->withHeader('Content-Length', 0);
            return;
        }

        $contentEncoding = strtolower($message->getHeaderLine('Content-Encoding'));

        if ('' !== $contentEncoding) {
            switch ($contentEncoding) {
                case 'deflate':
                    $stream = new ZlibEncoder(ZlibEncoder::DEFLATE);
                    break;

                case 'gzip':
                    $stream = new ZlibEncoder(ZlibEncoder::GZIP);
                    break;

                default:
                    throw new MessageException(
                        Response::BAD_REQUEST,
                        sprintf('Unsupported content encoding set: %s', $contentEncoding)
                    );
            }

            yield Stream\pipe($message->getBody(), $stream, true, 0, null, $timeout);
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

            yield $message
                ->withBody($stream)
                ->withHeader('Transfer-Encoding', 'chunked');
            return;
        }

        yield $message;
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
    private function buildIncomingStream(Socket $socket, Message $message, $timeout = 0)
    {
        $stream = $message->getBody();

        if ($stream instanceof SeekableStream) {
            yield $stream->seek(0);
        }

        if (!$stream->isReadable()) {
            yield $message;
            return;
        }

        if (strtolower($message->getHeaderLine('Transfer-Encoding') === 'chunked')) {
            $stream = new ChunkedDecoder($this->hwm);
            $body = $message->getBody();
            $coroutine = new Coroutine(Stream\pipe($body, $stream, true, 0, null, $timeout));
            $coroutine->done(null, function () use ($socket) {
                $socket->close();
            });
            $message = $message->withBody($stream);
        } elseif ($message->hasHeader('Content-Length')) {
            $length = (int) $message->getHeaderLine('Content-Length');
            if (0 >= $length) {
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
            && strtolower($message->getHeaderLine('Connection')) !== 'close'
        ) {
            throw new MessageException(Response::LENGTH_REQUIRED, 'Content-Length header required.');
        }

        $contentEncoding = strtolower($message->getHeaderLine('Content-Encoding'));

        switch ($contentEncoding) {
            case 'deflate':
            case 'gzip':
                $stream = new ZlibDecoder();
                yield Stream\pipe($message->getBody(), $stream, true, 0, null, $timeout);
                yield $message->withBody($stream);
                return;

            case '':
                yield $message;
                return;

            default:
                throw new MessageException(
                    Response::LENGTH_REQUIRED,
                    sprintf('Unsupported content encoding received: %s', $contentEncoding)
                );
        }
    }
}
