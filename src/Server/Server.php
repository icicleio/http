<?php
namespace Icicle\Http\Server;

use Exception;
use Icicle\Coroutine\Coroutine;
use Icicle\Http\Builder\Builder;
use Icicle\Http\Builder\BuilderInterface;
use Icicle\Http\Encoder\Encoder;
use Icicle\Http\Encoder\EncoderInterface;
use Icicle\Http\Exception\Error;
use Icicle\Http\Exception\InvalidCallableError;
use Icicle\Http\Exception\InvalidValueException;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Exception\ParseException;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\Response;
use Icicle\Http\Message\ResponseInterface;
use Icicle\Http\Reader\Reader;
use Icicle\Http\Reader\ReaderInterface;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Socket\Client\ClientInterface as SocketClientInterface;
use Icicle\Socket\Server\ServerFactory;
use Icicle\Socket\Server\ServerFactoryInterface;
use Icicle\Socket\Server\ServerInterface as SocketServerInterface;

class Server implements ServerInterface
{
    /**
     * @var callable
     */
    private $onRequest;

    /**
     * @var callable|null
     */
    private $onInvalidRequest;

    /**
     * @var callable|null
     */
    private $onError;

    /**
     * @var callable|null
     */
    private $onUpgrade;

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
     * @param callable $onRequest
     * @param mixed[]|null $options
     */
    public function __construct(callable $onRequest, array $options = [])
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

        $this->onRequest = $onRequest;
    }

    /**
     * @param callable $onRequest
     */
    public function setRequestHandler(callable $onRequest)
    {
        $this->onRequest = $onRequest;
    }

    /**
     * @param callable|null $onInvalidRequest
     */
    public function setInvalidRequestHandler(callable $onInvalidRequest = null)
    {
        $this->onInvalidRequest = $onInvalidRequest;
    }

    /**
     * @param callable|null $onError
     */
    public function setErrorHandler(callable $onError = null)
    {
        $this->onError = $onError;
    }

    /**
     * @param callable|null $onUpgrade
     */
    public function setUpgradeHandler(callable $onUpgrade = null)
    {
        $this->onUpgrade = $onUpgrade;
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
     * @param string|int $address
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
     * @param \Icicle\Socket\Client\ClientInterface $client
     * @param int $cryptoMethod
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     */
    private function process(SocketClientInterface $client, $cryptoMethod, $timeout, $allowPersistent)
    {
        $count = 0;

        try {
            if (0 !== $cryptoMethod) {
                yield $client->enableCrypto($cryptoMethod, $timeout);
            }

            do {
                try {
                    /** @var \Icicle\Http\Message\RequestInterface $request */
                    $request = (yield $this->readRequest($client, $timeout));
                    ++$count;

                    /** @var \Icicle\Http\Message\ResponseInterface $response */
                    $response = (yield $this->createResponse($request, $client, $timeout, $allowPersistent));
                } catch (TimeoutException $exception) { // Request timeout.
                    if (0 < $count) {
                        return; // Keep-alive timeout expired.
                    }
                    $response = (yield $this->createErrorResponse(408, $client, $timeout));
                } catch (MessageException $exception) { // Bad request.
                    $response = (yield $this->createErrorResponse($exception->getCode(), $client, $timeout));
                } catch (InvalidValueException $exception) { // Invalid value in message header.
                    $response = (yield $this->createErrorResponse(400, $client, $timeout));
                } catch (ParseException $exception) { // Parse error in request.
                    $response = (yield $this->createErrorResponse(400, $client, $timeout));
                }

                yield $client->write($this->encoder->encodeResponse($response));

                $stream = $response->getBody();

                if ($stream->isReadable() && (!isset($request) || $request->getMethod() !== 'HEAD')) {
                    yield $stream->pipe($client, false, 0, null, $timeout);
                }

                $connection = strtolower($response->getHeaderLine('Connection'));

                if ($connection === 'upgrade') {
                    if (!isset($request) || strtolower($request->getHeaderLine('Connection')) !== 'upgrade') {
                        throw new Error('Cannot upgrade connection without a valid upgrade request.');
                    }

                    yield $this->upgrade($request, $response, $client);
                    return;
                }
            } while ($allowPersistent
                && $connection === 'keep-alive'
                && $client->isReadable()
                && $client->isWritable()
            );
        } catch (Exception $exception) {
            yield $this->error($exception, $client);
        } finally {
            $client->close();
        }
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param \Icicle\Http\Message\ResponseInterface $response
     * @param \Icicle\Socket\Client\ClientInterface $client
     *
     * @return \Generator
     */
    private function upgrade(RequestInterface $request, ResponseInterface $response, SocketClientInterface $client)
    {
        if (null === $this->onUpgrade) {
            throw new Error('No callback given for upgrade responses.');
        }

        $onUpgrade = $this->onUpgrade;
        yield $onUpgrade($request, $response, $client);
    }

    /**
     * @coroutine
     *
     * @param \Exception $exception
     * @param \Icicle\Socket\Client\ClientInterface $client
     *
     * @return \Generator
     */
    private function error(Exception $exception, SocketClientInterface $client)
    {
        if (null !== $this->onError) {
            $onError = $this->onError;
            yield $onError($exception, $client);
        }
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Client\ClientInterface $client
     * @param float $timeout
     *
     * @return \Generator
     */
    private function readRequest(SocketClientInterface $client, $timeout)
    {
        $request = (yield $this->reader->readRequest($client, $timeout));

        yield $this->builder->buildIncomingRequest($request, $timeout);
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param \Icicle\Socket\Client\ClientInterface $client
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message|ResponseInterface
     */
    private function createResponse(
        RequestInterface $request,
        SocketClientInterface $client,
        $timeout,
        $allowPersistent
    ) {
        try {
            $onRequest = $this->onRequest;
            $response = (yield $onRequest($request, $client));

            if (!$response instanceof ResponseInterface) {
                throw new InvalidCallableError(
                    sprintf('A %s object was not returned from the request callback.', ResponseInterface::class),
                    $this->onRequest
                );
            }
        } catch (Exception $exception) {
            yield $this->error($exception, $client);
            $response = (yield $this->createDefaultErrorResponse(500));
        }

        yield $this->builder->buildOutgoingResponse($response, $request, $timeout, $allowPersistent);
    }

    /**
     * @coroutine
     *
     * @param int $code
     * @param \Icicle\Socket\Client\ClientInterface $client
     * @param float $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message|ResponseInterface
     */
    private function createErrorResponse($code, SocketClientInterface $client, $timeout)
    {
        if (null === $this->onInvalidRequest) {
            $response = (yield $this->createDefaultErrorResponse($code));
        } else {
            try {
                $onInvalidRequest = $this->onInvalidRequest;
                $response = (yield $onInvalidRequest($code, $client));

                if (!$response instanceof ResponseInterface) {
                    throw new InvalidCallableError(
                        sprintf('A %s object was not returned from the error callback.', ResponseInterface::class),
                        $this->onInvalidRequest
                    );
                }
            } catch (Exception $exception) {
                yield $this->error($exception, $client);
                $response = (yield $this->createDefaultErrorResponse(500));
            }
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
        $message = sprintf('%d Error', $code);

        $response = new Response($code, [
            'Connection' => 'close',
            'Content-Type' => 'text/plain',
            'Content-Length' => strlen($message),
        ]);

        yield $response->getBody()->end($message);

        yield $response;
    }
}
