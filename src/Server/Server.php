<?php
namespace Icicle\Http\Server;

use Exception;
use Icicle\Coroutine\Coroutine;
use Icicle\Http\Builder\Builder;
use Icicle\Http\Builder\BuilderInterface;
use Icicle\Http\Encoder\Encoder;
use Icicle\Http\Encoder\EncoderInterface;
use Icicle\Http\Exception\Error;
use Icicle\Http\Exception\InvalidResultError;
use Icicle\Http\Exception\InvalidValueException;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Exception\ParseException;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\Response;
use Icicle\Http\Message\ResponseInterface;
use Icicle\Http\Reader\Reader;
use Icicle\Http\Reader\ReaderInterface;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Stream;
use Icicle\Stream\MemorySink;
use Icicle\Stream\WritableStreamInterface;
use Icicle\Socket\Server\ServerFactory;
use Icicle\Socket\Server\ServerFactoryInterface;
use Icicle\Socket\Server\ServerInterface as SocketServerInterface;
use Icicle\Socket\SocketInterface;

class Server implements ServerInterface
{
    /**
     * @var \Icicle\Http\Server\RequestHandlerInterface
     */
    private $handler;

    /**
     * @var \Icicle\Stream\WritableStreamInterface
     */
    private $errorStream;

    /**
     * @var \Icicle\Http\Encoder\EncoderInterface
     */
    private $encoder;

    /**
     * @var \Icicle\Http\Reader\ReaderInterface
     */
    private $reader;

    /**
     * @var \Icicle\Socket\Server\ServerFactoryInterface
     */
    private $factory;

    /**
     * @var \Icicle\Http\Builder\Builder
     */
    private $builder;

    /**
     * @var \Icicle\Socket\Server\ServerInterface[]
     */
    private $servers = [];

    /**
     * @var bool
     */
    private $open = true;

    /**
     * @param \Icicle\Http\Server\RequestHandlerInterface $handler
     * @param \Icicle\Stream\WritableStreamInterface|null $log
     * @param mixed[] $options
     */
    public function __construct(RequestHandlerInterface $handler, WritableStreamInterface $log = null, array $options = [])
    {
        $this->reader = isset($options['reader']) && $options['reader'] instanceof ReaderInterface
            ? $options['reader']
            : new Reader($options);

        $this->builder = isset($options['builder']) && $options['builder'] instanceof BuilderInterface
            ? $options['builder']
            : new Builder($options);

        $this->encoder = isset($options['encoder']) && $options['encoder'] instanceof EncoderInterface
            ? $options['encoder']
            : new Encoder();

        $this->factory = isset($options['factory']) && $options['factory'] instanceof ServerFactoryInterface
            ? $options['factory']
            : new ServerFactory();

        $this->handler = $handler;
        $this->errorStream = $log ?: Stream\stderr();
    }

    /**
     * @return bool
     */
    public function isOpen()
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
     * @param string $address
     * @param int $port
     * @param mixed[] $options
     *
     * @throws \Icicle\Http\Exception\Error If the server has been closed.
     *
     * @see \Icicle\Socket\Server\ServerFactoryInterface::create() Options are similar to this method with the
     *     addition of the crypto_method option.
     */
    public function listen($port, $address = self::DEFAULT_ADDRESS, array $options = [])
    {
        $cryptoMethod = isset($options['crypto_method'])
            ? (int) $options['crypto_method']
            : (isset($options['pem']) ? self::DEFAULT_CRYPTO_METHOD : 0);
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;

        if (!$this->open) {
            throw new Error('The server has been closed.');
        }

        $server = $this->factory->create($address, $port, $options);

        $this->servers[] = $server;

        $coroutine = new Coroutine($this->accept($server, $cryptoMethod, $timeout, $allowPersistent));
        $coroutine->done();
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Server\ServerInterface $server
     * @param int $cryptoMethod
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     */
    private function accept(SocketServerInterface $server, $cryptoMethod, $timeout, $allowPersistent)
    {
        while ($server->isOpen()) {
            try {
                $coroutine = new Coroutine(
                    $this->process((yield $server->accept()), $cryptoMethod, $timeout, $allowPersistent)
                );
                $coroutine->done();
            } catch (Exception $exception) {
                if ($this->open) {
                    $this->close();
                    throw $exception;
                }
            }
        }
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\SocketInterface $socket
     * @param int $cryptoMethod
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve null
     */
    private function process(SocketInterface $socket, $cryptoMethod, $timeout, $allowPersistent)
    {
        $count = 0;

        try {
            if (0 !== $cryptoMethod) {
                yield $socket->enableCrypto($cryptoMethod, $timeout);
            }

            do {
                try {
                    /** @var \Icicle\Http\Message\RequestInterface $request */
                    $request = (yield $this->readRequest($socket, $timeout));
                    ++$count;

                    /** @var \Icicle\Http\Message\ResponseInterface $response */
                    $response = (yield $this->createResponse($request, $socket, $timeout, $allowPersistent));
                } catch (TimeoutException $exception) { // Request timeout.
                    if (0 < $count) {
                        return; // Keep-alive timeout expired.
                    }
                    $response = (yield $this->createErrorResponse(ResponseInterface::STATUS_REQUEST_TIMEOUT, $socket, $timeout));
                } catch (MessageException $exception) { // Bad request.
                    $response = (yield $this->createErrorResponse($exception->getCode(), $socket, $timeout));
                } catch (InvalidValueException $exception) { // Invalid value in message header.
                    $response = (yield $this->createErrorResponse(ResponseInterface::STATUS_BAD_REQUEST, $socket, $timeout));
                } catch (ParseException $exception) { // Parse error in request.
                    $response = (yield $this->createErrorResponse(ResponseInterface::STATUS_BAD_REQUEST, $socket, $timeout));
                }

                yield $socket->write($this->encoder->encodeResponse($response));

                $stream = $response->getBody();

                if ($stream->isReadable() && (!isset($request) || $request->getMethod() !== 'HEAD')) {
                    yield Stream\pipe($stream, $socket, false, 0, null, $timeout);
                }

                $connection = strtolower($response->getHeaderLine('Connection'));

                if ($connection === 'upgrade') {
                    if (!isset($request) || strtolower($request->getHeaderLine('Connection')) !== 'upgrade') {
                        throw new Error('Cannot upgrade connection without a valid upgrade request.');
                    }

                    if (!$this->handler instanceof UpgradeHandlerInterface) {
                        throw new Error('Request handler cannot process upgrade requests.');
                    }

                    yield $this->handler->onUpgrade($request, $response, $socket);
                    return; // Request done.
                }
            } while ($allowPersistent
                && $connection === 'keep-alive'
                && $socket->isReadable()
                && $socket->isWritable()
            );
        } catch (Exception $exception) {
            yield $this->errorStream->write(sprintf(
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
     * @param \Icicle\Socket\SocketInterface $client
     * @param float $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\RequestInterface
     */
    private function readRequest(SocketInterface $client, $timeout)
    {
        $request = (yield $this->reader->readRequest($client, $timeout));

        yield $this->builder->buildIncomingRequest($request, $timeout);
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param \Icicle\Socket\SocketInterface $socket
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message|ResponseInterface
     */
    private function createResponse(
        RequestInterface $request,
        SocketInterface $socket,
        $timeout,
        $allowPersistent
    ) {
        try {
            $response = (yield $this->handler->onRequest($request, $socket));

            if (!$response instanceof ResponseInterface) {
                throw new InvalidResultError(
                    sprintf('A %s object was not returned from the request callback.', ResponseInterface::class),
                    $response
                );
            }
        } catch (Exception $exception) {
            yield $this->errorStream->write(sprintf(
                "Uncaught exception when creating response to a request from %s:%d in file %s on line %d: %s\n",
                $socket->getRemoteAddress(),
                $socket->getRemotePort(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage()
            ));
            $response = (yield $this->createDefaultErrorResponse(500));
        }

        yield $this->builder->buildOutgoingResponse($response, $request, $timeout, $allowPersistent);
    }

    /**
     * @coroutine
     *
     * @param int $code
     * @param \Icicle\Socket\SocketInterface $socket
     * @param float $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message|ResponseInterface
     */
    private function createErrorResponse($code, SocketInterface $socket, $timeout)
    {
        try {
            $response = (yield $this->handler->onError($code, $socket));

            if (!$response instanceof ResponseInterface) {
                throw new InvalidResultError(
                    sprintf('A %s object was not returned from the error callback.', ResponseInterface::class),
                    $response
                );
            }
        } catch (Exception $exception) {
            yield $this->errorStream->write(sprintf(
                "Uncaught exception when creating response to an error from %s:%d in file %s on line %d: %s\n",
                $socket->getRemoteAddress(),
                $socket->getRemotePort(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage()
            ));
            $response = (yield $this->createDefaultErrorResponse(500));
        }

        yield $this->builder->buildOutgoingResponse($response, null, $timeout, false);
    }

    /**
     * @coroutine
     *
     * @param int $code
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface
     */
    protected function createDefaultErrorResponse($code)
    {
        $sink = new MemorySink(sprintf('%d Error', $code));

        $headers = [
            'Connection' => 'close',
            'Content-Type' => 'text/plain',
            'Content-Length' => $sink->getLength(),
        ];

        return new Response($code, $headers, $sink);
    }
}
