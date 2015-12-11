<?php
namespace Icicle\Http\Driver;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
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
    public function readRequest(Socket $socket, $timeout = 0)
    {
        $request = (yield $this->reader->readRequest($socket, $timeout));

        yield $this->builder->buildIncomingRequest($socket, $request, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildResponse(
        Socket $socket,
        Response $response,
        Request $request = null,
        $timeout = 0,
        $allowPersistent = false
    ) {
        return $this->builder->buildOutgoingResponse(
            $socket, $response, $request, $timeout, $allowPersistent
        );
    }

    /**
     * {@inheritdoc}
     */
    public function writeResponse(
        Socket $socket,
        Response $response,
        Request $request = null,
        $timeout = 0
    ) {
        $written = (yield $socket->write($this->encoder->encodeResponse($response)));

        $stream = $response->getBody();

        try {
            if ((!isset($request) || $request->getMethod() !== 'HEAD') && $stream->isReadable()) {
                $written += (yield Stream\pipe($stream, $socket, false, 0, null, $timeout));
            }
        } finally {
            $stream->close();
        }

        yield $written;
    }

    /**
     * {@inheritdoc}
     */
    public function readResponse(Socket $socket, $timeout = 0)
    {
        $request = (yield $this->reader->readResponse($socket, $timeout));

        yield $this->builder->buildIncomingResponse($socket, $request, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function buildRequest(Socket $socket, Request $request, $timeout = 0, $allowPersistent = false)
    {
        return $this->builder->buildOutgoingRequest($socket, $request, $timeout, $allowPersistent);
    }

    /**
     * {@inheritdoc}
     */
    public function writeRequest(Socket $socket, Request $request, $timeout = 0)
    {
        $written = (yield $socket->write($this->encoder->encodeRequest($request)));

        $stream = $request->getBody();

        try {
            if ($stream->isReadable()) {
                $written += (yield Stream\pipe($stream, $socket, false, 0, null, $timeout));
            }
        } finally {
            $stream->close();
        }

        yield $written;
    }
}