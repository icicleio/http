<?php
namespace Icicle\Http\Driver;

use Icicle\Http\Message\{Request, Response};
use Icicle\Socket\Socket;
use Icicle\Stream;

class Http1Driver implements Driver
{
    /**
     * @var \Icicle\Http\Driver\Reader\Reader
     */
    private $reader;

    /**
     * @var \Icicle\Http\Driver\Encoder\Encoder
     */
    private $encoder;

    /**
     * @var \Icicle\Http\Driver\Builder\Builder
     */
    private $builder;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        $this->reader = new Reader\Http1Reader($options);
        $this->encoder = new Encoder\Http1Encoder();
        $this->builder = new Builder\Http1Builder($options);
    }

    /**
     * {@inheritdoc}
     */
    public function readRequest(Socket $socket, float $timeout = 0): \Generator
    {
        $request = yield from $this->reader->readRequest($socket, $timeout);

        return yield from $this->builder->buildIncomingRequest($request, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildResponse(
        Response $response,
        Request $request = null,
        float $timeout = 0,
        bool $allowPersistent = false
    ): \Generator {
        return $this->builder->buildOutgoingResponse(
            $response, $request, $timeout, $allowPersistent
        );
    }

    /**
     * {@inheritdoc}
     */
    public function writeResponse(
        Socket $socket,
        Response $response,
        Request $request = null,
        float $timeout = 0
    ): \Generator {
        $written = yield from $socket->write($this->encoder->encodeResponse($response));

        $stream = $response->getBody();

        if ((!isset($request) || $request->getMethod() !== 'HEAD') && $stream->isReadable()) {
            $written += yield from Stream\pipe($stream, $socket, false, 0, null, $timeout);
        }

        return $written;
    }

    /**
     * {@inheritdoc}
     */
    public function readResponse(Socket $socket, float $timeout = 0): \Generator
    {
        $request = yield from $this->reader->readResponse($socket, $timeout);

        return yield from $this->builder->buildIncomingResponse($request, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildRequest(Request $request, float $timeout = 0, bool $allowPersistent = false): \Generator
    {
        return $this->builder->buildOutgoingRequest($request, $timeout, $allowPersistent);
    }

    /**
     * {@inheritdoc}
     */
    public function writeRequest(Socket $socket, Request $request, float $timeout = 0): \Generator
    {
        $written = yield from $socket->write($this->encoder->encodeRequest($request));

        $stream = $request->getBody();

        if ($stream->isReadable()) {
            $written += yield from Stream\pipe($stream, $socket, false, 0, null, $timeout);
        }

        return $written;
    }
}