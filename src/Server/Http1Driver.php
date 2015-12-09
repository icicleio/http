<?php
namespace Icicle\Http\Server;

use Icicle\Awaitable\Exception\TimeoutException;
use Icicle\Coroutine\Coroutine;
use Icicle\Http\Builder\Http1Builder;
use Icicle\Http\Encoder\Http1Encoder;
use Icicle\Http\Exception\InvalidResultError;
use Icicle\Http\Exception\InvalidValueException;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Exception\ParseException;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Http\Reader\Http1Reader;
use Icicle\Socket\Socket;
use Icicle\Stream;
use Icicle\Stream\MemorySink;
use Icicle\Stream\WritableStream;

class Http1Driver implements Driver
{
    /**
     * @var \Icicle\Http\Server\RequestHandler
     */
    private $handler;

    /**
     * @var \Icicle\Http\Reader\Reader
     */
    private $reader;

    /**
     * @var \Icicle\Http\Encoder\Encoder
     */
    private $encoder;

    /**
     * @var \Icicle\Http\Builder\Builder
     */
    private $builder;

    /**
     * @var \Icicle\Stream\WritableStream
     */
    private $log;

    /**
     * @param RequestHandler $handler
     * @param WritableStream|null $log
     * @param mixed[] $options
     */
    public function __construct(
        RequestHandler $handler,
        WritableStream $log = null,
        array $options = []
    ) {
        $this->handler = $handler;
        $this->log = $log ?: Stream\stderr();

        $this->reader = new Http1Reader($options);
        $this->encoder = new Http1Encoder();
        $this->builder = new Http1Builder($options);
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param int $cryptoMethod
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve null
     */
    public function process(Socket $socket, $cryptoMethod, $timeout, $allowPersistent)
    {
        $count = 0;

        try {
            if (0 !== $cryptoMethod) {
                yield $socket->enableCrypto($cryptoMethod, $timeout);
            }

            do {
                try {
                    /** @var \Icicle\Http\Message\Request $request */
                    $request = (yield $this->readRequest($socket, $timeout));
                    ++$count;

                    /** @var \Icicle\Http\Message\Response $response */
                    $response = (yield $this->createResponse($request, $socket, $timeout, $allowPersistent));
                } catch (TimeoutException $exception) { // Request timeout.
                    if (0 < $count) {
                        return; // Keep-alive timeout expired.
                    }
                    $response = (yield $this->createErrorResponse(Response::REQUEST_TIMEOUT, $socket, $timeout));
                } catch (MessageException $exception) { // Bad request.
                    $response = (yield $this->createErrorResponse($exception->getCode(), $socket, $timeout));
                } catch (InvalidValueException $exception) { // Invalid value in message header.
                    $response = (yield $this->createErrorResponse(Response::BAD_REQUEST, $socket, $timeout));
                } catch (ParseException $exception) { // Parse error in request.
                    $response = (yield $this->createErrorResponse(Response::BAD_REQUEST, $socket, $timeout));
                }

                $coroutine = new Coroutine($this->writeResponse(
                    $response,
                    $socket,
                    !isset($request) || $request->getMethod() !== 'HEAD',
                    $timeout
                ));
            } while ($allowPersistent
                && strtolower($response->getHeaderLine('Connection')) === 'keep-alive'
                && $socket->isReadable()
                && $socket->isWritable()
            );

            yield $coroutine; // Wait until response has completed writing.
        } catch (\Exception $exception) {
            yield $this->log->write(sprintf(
                "Error when handling request from %s:%d: %s\n",
                $socket->getRemoteAddress(),
                $socket->getRemotePort(),
                $exception->getMessage()
            ));
        } finally {
            $socket->close();
        }
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param float $timeout
     *
     * @return \Generator
     */
    public function readRequest(Socket $socket, $timeout)
    {
        $request = (yield $this->reader->readRequest($socket, $timeout));

        yield $this->builder->buildIncomingRequest($socket, $request, $timeout);
    }

    /**
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Socket\Socket $socket
     * @param bool $body
     * @param float $timeout
     *
     * @return \Generator
     *
     * @throws Stream\Exception\UnwritableException
     * @throws \Icicle\Exception\InvalidArgumentError
     */
    public function writeResponse(Response $response, Socket $socket, $body, $timeout)
    {
        yield $socket->write($this->encoder->encodeResponse($response));

        $stream = $response->getBody();

        try {
            if ($body && $stream->isReadable()) {
                yield Stream\pipe($stream, $socket, false, 0, null, $timeout);
            }
        } finally {
            $stream->close();
        }
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message|Response
     */
    public function createResponse(
        Request $request,
        Socket $socket,
        $timeout,
        $allowPersistent
    ) {
        try {
            $response = (yield $this->handler->onRequest($request, $socket));

            if (!$response instanceof Response) {
                throw new InvalidResultError(
                    sprintf('A %s object was not returned from the request callback.', Response::class),
                    $response
                );
            }
        } catch (\Exception $exception) {
            yield $this->log->write(sprintf(
                "Uncaught exception when creating response to a request from %s:%d in file %s on line %d: %s\n",
                $socket->getRemoteAddress(),
                $socket->getRemotePort(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage()
            ));
            $response = (yield $this->createDefaultErrorResponse(500));
        }

        yield $this->builder->buildOutgoingResponse($socket, $response, $request, $timeout, $allowPersistent);
    }

    /**
     * @coroutine
     *
     * @param int $code
     * @param \Icicle\Socket\Socket $socket
     * @param float $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message|Response
     */
    private function createErrorResponse($code, Socket $socket, $timeout)
    {
        try {
            $response = (yield $this->handler->onError($code, $socket));

            if (!$response instanceof Response) {
                throw new InvalidResultError(
                    sprintf('A %s object was not returned from the error callback.', Response::class),
                    $response
                );
            }
        } catch (\Exception $exception) {
            yield $this->log->write(sprintf(
                "Uncaught exception when creating response to an error from %s:%d in file %s on line %d: %s\n",
                $socket->getRemoteAddress(),
                $socket->getRemotePort(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage()
            ));
            $response = (yield $this->createDefaultErrorResponse(500));
        }

        yield $this->builder->buildOutgoingResponse($socket, $response, null, $timeout, false);
    }

    /**
     * @coroutine
     *
     * @param int $code
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    protected function createDefaultErrorResponse($code)
    {
        $sink = new MemorySink(sprintf('%d Error', $code));

        $headers = [
            'Connection' => 'close',
            'Content-Type' => 'text/plain',
            'Content-Length' => $sink->getLength(),
        ];

        return new BasicResponse($code, $headers, $sink);
    }}
