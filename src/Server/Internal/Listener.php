<?php
namespace Icicle\Http\Server\Internal;

use Icicle\Awaitable\{Awaitable, Exception\TimeoutException};
use Icicle\Coroutine\Coroutine;
use Icicle\Http\{Driver\Driver, Server\RequestHandler};
use Icicle\Http\Exception\{ClosedError, InvalidResultError, InvalidValueException, MessageException, ParseException};
use Icicle\Http\Message\{BasicResponse, Request, Response};
use Icicle\Log\Log;
use Icicle\Socket\{Server\ServerFactory, Server\Server, Socket};
use Icicle\Stream\MemorySink;
use Throwable;

class Listener
{
    const DEFAULT_CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLS_SERVER;
    const DEFAULT_TIMEOUT = 15;

    /**
     * @var \Icicle\Http\Server\RequestHandler
     */
    private $handler;

    /**
     * @var \Icicle\Http\Driver\Driver
     */
    private $driver;

    /**
     * @var \Icicle\Socket\Server\ServerFactory
     */
    private $factory;

    /**
     * @var \Icicle\Socket\Server\Server[]
     */
    private $servers = [];

    /**
     * @var \Icicle\Log\Log
     */
    private $log;

    /**
     * @var bool
     */
    private $open = true;

    /**
     * @param \Icicle\Http\Driver\Driver $driver
     * @param \Icicle\Http\Server\RequestHandler $handler
     * @param \Icicle\Log\Log $log
     * @param \Icicle\Socket\Server\ServerFactory $factory
     */
    public function __construct(
        Driver $driver,
        RequestHandler $handler,
        Log $log,
        ServerFactory $factory
    ) {
        $this->driver = $driver;
        $this->handler = $handler;
        $this->log = $log;
        $this->factory = $factory;
    }

    /**
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * Closes all listening servers.
     */
    public function close()
    {
        $this->open = false;

        foreach ($this->servers as $server) {
            $server->close();
        }
    }

    /**
     * @param int $port
     * @param string $address
     * @param mixed[] $options
     *
     * @throws \Icicle\Http\Exception\ClosedError If the server has been closed.
     *
     * @see \Icicle\Socket\Server\ServerFactory::create() Options are similar to this method with the
     *     addition of the crypto_method option.
     */
    public function listen(int $port, string $address, array $options = [])
    {
        if (!$this->open) {
            throw new ClosedError('The server has been closed.');
        }

        $cryptoMethod = isset($options['crypto_method'])
            ? (int) $options['crypto_method']
            : (isset($options['pem']) ? self::DEFAULT_CRYPTO_METHOD : 0);
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;

        try {
            $server = $this->factory->create($address, $port, $options);
        } catch (Throwable $exception) {
            $this->close();
            throw $exception;
        }

        $this->servers[] = $server;

        $coroutine = new Coroutine($this->accept($server, $cryptoMethod, $timeout, $allowPersistent));
        $coroutine->done();
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Server\Server $server
     * @param int $cryptoMethod
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     */
    private function accept(Server $server, int $cryptoMethod, float $timeout, bool $allowPersistent): \Generator
    {
        yield from $this->log->log(
            Log::INFO,
            'HTTP server listening on %s:%d',
            $server->getAddress(),
            $server->getPort()
        );

        while ($server->isOpen()) {
            try {
                $coroutine = new Coroutine(
                    $this->process(yield from $server->accept(), $cryptoMethod, $timeout, $allowPersistent)
                );
                $coroutine->done();
            } catch (Throwable $exception) {
                if ($this->open) {
                    $this->close();
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param \Icicle\Socket\Socket $socket
     * @param int $cryptoMethod
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve null
     */
    private function process(Socket $socket, int $cryptoMethod, float $timeout, bool $allowPersistent): \Generator
    {
        $count = 0;

        assert(yield from $this->log->log(
            Log::DEBUG,
            'Accepted client from %s:%d on %s:%d',
            $socket->getRemoteAddress(),
            $socket->getRemotePort(),
            $socket->getLocalAddress(),
            $socket->getLocalPort()
        ));

        try {
            if (0 !== $cryptoMethod) {
                yield from $socket->enableCrypto($cryptoMethod, $timeout);
            }

            do {
                $request = null;

                try {
                    /** @var \Icicle\Http\Message\Request $request */
                    $request = yield from $this->driver->readRequest($socket, $timeout);
                    ++$count;

                    /** @var \Icicle\Http\Message\Response $response */
                    $response = yield from $this->createResponse($request, $socket);

                    assert(yield from $this->log->log(
                        Log::DEBUG,
                        'Responded to request from %s:%d for %s with %d %s',
                        $socket->getRemoteAddress(),
                        $socket->getRemotePort(),
                        $request->getUri(),
                        $response->getStatusCode(),
                        $response->getReasonPhrase()
                    ));
                } catch (TimeoutException $exception) { // Request timeout.
                    if (0 < $count) {
                        assert(yield from $this->log->log(
                            Log::DEBUG,
                            'Keep-alive timeout from %s:%d on %s:%d',
                            $socket->getRemoteAddress(),
                            $socket->getRemotePort(),
                            $socket->getLocalAddress(),
                            $socket->getLocalPort()
                        ));
                        return; // Keep-alive timeout expired.
                    }
                    $response = yield from $this->createErrorResponse(Response::REQUEST_TIMEOUT, $socket);
                } catch (MessageException $exception) { // Bad request.
                    $response = yield from $this->createErrorResponse($exception->getCode(), $socket);
                } catch (InvalidValueException $exception) { // Invalid value in message header.
                    $response = yield from $this->createErrorResponse(Response::BAD_REQUEST, $socket);
                } catch (ParseException $exception) { // Parse error in request.
                    $response = yield from $this->createErrorResponse(Response::BAD_REQUEST, $socket);
                }

                $response = yield from $this->driver->buildResponse(
                    $response,
                    $request,
                    $timeout,
                    $allowPersistent
                );

                try {
                    yield from $this->driver->writeResponse($socket, $response, $request, $timeout);
                } finally {
                    $response->getBody()->close();
                }
            } while (strtolower($response->getHeader('Connection')) === 'keep-alive');
        } catch (Throwable $exception) {
            yield from $this->log->log(
                Log::NOTICE,
                "Error when handling request from %s:%d: %s",
                $socket->getRemoteAddress(),
                $socket->getRemotePort(),
                $exception->getMessage()
            );
        } finally {
            $socket->close();
        }

        assert(yield from $this->log->log(
            Log::DEBUG,
            'Disconnected client from %s:%d on %s:%d',
            $socket->getRemoteAddress(),
            $socket->getRemotePort(),
            $socket->getLocalAddress(),
            $socket->getLocalPort()
        ));
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message|Response
     */
    private function createResponse(Request $request, Socket $socket): \Generator
    {
        try {
            assert(yield from $this->log->log(
                Log::DEBUG,
                'Received request from %s:%d for %s',
                $socket->getRemoteAddress(),
                $socket->getRemotePort(),
                $request->getUri()
            ));

            $response = $this->handler->onRequest($request, $socket);

            if ($response instanceof \Generator) {
                $response = yield from $response;
            } elseif ($response instanceof Awaitable) {
                $response = yield $response;
            }

            if (!$response instanceof Response) {
                throw new InvalidResultError(
                    sprintf('A %s object was not returned from the request callback.', Response::class),
                    $response
                );
            }
        } catch (Throwable $exception) {
            yield from $this->log->log(
                Log::ERROR,
                "Uncaught exception when creating response to a request from %s:%d on %s:%d in file %s on line %d: %s",
                $socket->getRemoteAddress(),
                $socket->getRemotePort(),
                $socket->getLocalAddress(),
                $socket->getLocalPort(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage()
            );
            $response = yield from $this->createDefaultErrorResponse(500);
        }

        return $response;
    }

    /**
     * @coroutine
     *
     * @param int $code
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message|Response
     */
    private function createErrorResponse(int $code, Socket $socket): \Generator
    {
        try {
            yield from $this->log->log(
                Log::NOTICE,
                'Error reading request from %s:%d (Status Code: %d)',
                $socket->getRemoteAddress(),
                $socket->getRemotePort(),
                $code
            );

            $response = $this->handler->onError($code, $socket);

            if ($response instanceof \Generator) {
                $response = yield from $response;
            } elseif ($response instanceof Awaitable) {
                $response = yield $response;
            }

            if (!$response instanceof Response) {
                throw new InvalidResultError(
                    sprintf('A %s object was not returned from the error callback.', Response::class),
                    $response
                );
            }
        } catch (Throwable $exception) {
            yield from $this->log->log(
                Log::ERROR,
                "Uncaught exception when creating response to an error from %s:%d on %s:%d in file %s on line %d: %s",
                $socket->getRemoteAddress(),
                $socket->getRemotePort(),
                $socket->getLocalAddress(),
                $socket->getLocalPort(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage()
            );
            $response = yield from $this->createDefaultErrorResponse(500);
        }

        return $response;
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
    protected function createDefaultErrorResponse(int $code): \Generator
    {
        $sink = new MemorySink(sprintf('%d Error', $code));

        $headers = [
            'Connection' => 'close',
            'Content-Type' => 'text/plain',
            'Content-Length' => $sink->getLength(),
        ];

        return new BasicResponse($code, $headers, $sink);
        yield; // Unreachable, but makes method a coroutine.
    }
}
