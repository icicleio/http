<?php
namespace Icicle\Http\Builder;

use Icicle\Http\Exception\LengthRequiredException;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Message\MessageInterface;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\ResponseInterface;
use Icicle\Http\Stream\ChunkedDecoder;
use Icicle\Http\Stream\ChunkedEncoder;
use Icicle\Http\Stream\LimitStream;
use Icicle\Http\Stream\ZlibDecoder;
use Icicle\Http\Stream\ZlibEncoder;
use Icicle\Stream\SeekableStreamInterface;

class Builder implements BuilderInterface
{
    const DEFAULT_STREAM_HWM = 8192;

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
     * @param   mixed[] $options
     */
    public function __construct(array $options = null)
    {
        if (isset($options['compress_types']) && is_array($options['compress_types'])) {
            $this->compressTypes = $options['compress_types'];
        }

        if (isset($options['hwm'])) {
            $this->hwm = (int) $options['hwm'];
        }

        $this->compressionEnabled = !isset($options['disable_compression']) ? extension_loaded('zlib') : false;
    }

    /**
     * @inheritdoc
     */
    public function buildOutgoingResponse(
        ResponseInterface $response,
        RequestInterface $request = null,
        $timeout = null,
        $allowPersistent = false
    ) {
        if (null === $request) { // Fallback to 1.0 for responses due to message errors.
            $response = $response->withProtocolVersion('1.0');
        } elseif ($request->getProtocolVersion() !== $response->getProtocolVersion()) {
            $response = $response->withProtocolVersion($request->getProtocolVersion());
        }

        if ($response->getProtocolVersion() === '1.1'
            && !$response->hasHeader('Connection')
            && $allowPersistent
            && strtolower($request->getHeaderLine('Connection')) === 'keep-alive'
        ) {
            $response = $response
                ->withHeader('Connection', 'keep-alive')
                ->withHeader('Keep-Alive', sprintf('timeout=%d', $timeout));
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

        yield $this->buildOutgoingStream($response, $timeout);
    }

    /**
     * @inheritdoc
     */
    public function buildOutgoingRequest(
        RequestInterface $request,
        $timeout = null,
        $allowPersistent = false
    ) {
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

        yield $this->buildOutgoingStream($request, $timeout);
    }

    /**
     * @inheritdoc
     */
    public function buildIncomingRequest(RequestInterface $request, $timeout = null)
    {
        if ($request->getMethod() === 'POST' || $request->getMethod() === 'PUT') {
            yield $this->buildIncomingStream($request, $timeout);
            return;
        }

        yield $request->withBody(new LimitStream(0)); // No body in other requests.
    }

    /**
     * @inheritdoc
     */
    public function buildIncomingResponse(ResponseInterface $response, $timeout = null)
    {
        yield $this->buildIncomingStream($response, $timeout);
    }

    /**
     * @param   \Icicle\Http\Message\MessageInterface $message
     * @param   float|null $timeout
     *
     * @return  \Generator
     *
     * @resolve \Icicle\Http\Message\MessageInterface
     */
    private function buildOutgoingStream(MessageInterface $message, $timeout = null)
    {
        $stream = $message->getBody();

        if ($stream instanceof SeekableStreamInterface) {
            $stream->seek(0);
        }

        if (!$stream->isReadable() || strtolower($message->getHeaderLine('Connection')) === 'upgrade') {
            yield $message;
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
                        sprintf('Unsupported content encoding set: %s', $contentEncoding)
                    );
            }

            yield $message->getBody()->pipe($stream, true, null, null, $timeout);
            $message = $message
                ->withBody($stream)
                ->withoutHeader('Content-Length');
        }

        if ($message->getProtocolVersion() === '1.1' && !$message->hasHeader('Content-Length')) {
            $stream = new ChunkedEncoder($this->hwm);
            $message->getBody()->pipe($stream, true, null, null, $timeout);
            yield $message
                ->withBody($stream)
                ->withHeader('Transfer-Encoding', 'chunked');
            return;
        }

        yield $message;
        return;
    }

    /**
     * @param   \Icicle\Http\Message\MessageInterface $message
     * @param   float|null $timeout
     *
     * @return  \Generator
     *
     * @resolve \Icicle\Http\Message\MessageInterface
     */
    private function buildIncomingStream(MessageInterface $message, $timeout = null)
    {
        $stream = $message->getBody();

        if ($stream instanceof SeekableStreamInterface) {
            $stream->seek(0);
        }

        if (!$stream->isReadable() || strtolower($message->getHeaderLine('Connection') === 'upgrade')) {
            yield $message;
            return;
        }

        if (strtolower($message->getHeaderLine('Transfer-Encoding') === 'chunked')) {
            $stream = new ChunkedDecoder($this->hwm);
            $message->getBody()->pipe($stream, true, null, null, $timeout);
            $message = $message->withBody($stream);
        } elseif ($message->hasHeader('Content-Length')) {
            $stream = new LimitStream((int) $message->getHeaderLine('Content-Length'), $this->hwm);
            $message->getBody()->pipe($stream, true, null, null, $timeout);
            $message = $message->withBody($stream);
        } elseif (
            !$message instanceof ResponseInterface // ResponseInterface may have no length on incoming stream.
            && strtolower($message->getHeaderLine('Connection')) !== 'close'
        ) {
            throw new LengthRequiredException('Content-Length header required.');
        }

        $contentEncoding = strtolower($message->getHeaderLine('Content-Encoding'));

        switch ($contentEncoding) {
            case 'deflate':
            case 'gzip':
                $stream = new ZlibDecoder();
                yield $message->getBody()->pipe($stream, true, null, null, $timeout);
                yield $message->withBody($stream);
                return;

            case '':
                yield $message;
                return;

            default:
                throw new MessageException(
                    sprintf('Unsupported content encoding received: %s', $contentEncoding)
                );
        }
    }
}
