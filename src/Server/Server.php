<?php
namespace Icicle\Http\Server;

use Exception;
use Icicle\Coroutine\Coroutine;
use Icicle\Http\Builder\Builder;
use Icicle\Http\Builder\BuilderInterface;
use Icicle\Http\Encoder\Encoder;
use Icicle\Http\Encoder\EncoderInterface;
use Icicle\Http\Exception\InvalidCallableException;
use Icicle\Http\Exception\LengthRequiredException;
use Icicle\Http\Exception\LogicException;
use Icicle\Http\Exception\MessageBodySizeException;
use Icicle\Http\Exception\MessageHeaderSizeException;
use Icicle\Http\Exception\UnexpectedValueException;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\Response;
use Icicle\Http\Message\ResponseInterface;
use Icicle\Http\Reader\Reader;
use Icicle\Http\Reader\ReaderInterface;
use Icicle\Socket\Client\ClientInterface as SocketClientInterface;
use Icicle\Socket\Exception\ExceptionInterface as SocketException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Socket\Server\ServerFactory;
use Icicle\Socket\Server\ServerFactoryInterface;
use Icicle\Socket\Server\ServerInterface as SocketServerInterface;
use Icicle\Stream\Exception\ExceptionInterface as StreamException;

class Server implements ServerInterface
{
    const DEFAULT_ADDRESS = '127.0.0.1';
    const DEFAULT_MAX_HEADER_SIZE = 8192;
    const DEFAULT_TIMEOUT = 15;
    const DEFAULT_CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLS_SERVER;

    /**
     * @var callable
     */
    private $onRequest;

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
     * @var float|int
     */
    private $timeout = self::DEFAULT_TIMEOUT;

    /**
     * @var bool
     */
    private $allowPersistent = true;

    /**
     * @var int
     */
    private $maxHeaderSize = self::DEFAULT_MAX_HEADER_SIZE;

    /**
     * @var bool
     */
    private $open = true;

    /**
     * @param   callable $onRequest
     * @param   callable|null $onError
     * @param   callable|null $onUpgrade
     * @param   mixed[]|null $options
     */
    public function __construct(
        callable $onRequest,
        callable $onError = null,
        callable $onUpgrade = null,
        array $options = null
    ) {
        $this->timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $this->allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;
        $this->maxHeaderSize = isset($options['max_header_size'])
            ? (int) $options['max_header_size']
            : self::DEFAULT_MAX_HEADER_SIZE;

        $this->reader = isset($options['reader']) && $options['reader'] instanceof ReaderInterface
            ? $options['reader']
            : new Reader();

        $this->builder = isset($options['builder']) && $options['builder'] instanceof BuilderInterface
            ? $options['builder']
            : new Builder();

        $this->encoder = isset($options['encoder']) && $options['encoder'] instanceof EncoderInterface
            ? $options['encoder']
            : new Encoder();

        $this->factory = isset($options['factory']) && $options['factory'] instanceof ServerFactoryInterface
            ? $options['factory']
            : new ServerFactory();

        $this->onRequest = $onRequest;
        $this->onError =   $onError;
        $this->onUpgrade = $onUpgrade;
    }

    /**
     * @return  bool
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
     * @return  float|int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @return  bool
     */
    public function allowPersistent()
    {
        return $this->allowPersistent;
    }

    /**
     * @param   string|int $address
     * @param   int $port
     * @param   mixed[] $options
     *
     * @throws  \Icicle\Http\Exception\LogicException If the server has been closed.
     *
     * @see     \Icicle\Socket\Server\ServerFactoryInterface::create() Options are similar to this method with the
     *          addition of the crypto_method option.
     */
    public function listen($port, $address = self::DEFAULT_ADDRESS, array $options = null)
    {
        if (!$this->open) {
            throw new LogicException('The server has been closed.');
        }

        $server = $this->factory->create($address, $port, $options);

        $this->servers[] = $server;

        $cryptoMethod = isset($options['crypto_method'])
            ? (int) $options['crypto_method']
            : (isset($options['pem']) ? self::DEFAULT_CRYPTO_METHOD : 0);

        (new Coroutine($this->accept($server, $cryptoMethod)))->done();
    }

    /**
     * @coroutine
     *
     * @param   \Icicle\Socket\Server\ServerInterface $server
     * @param   int $cryptoMethod
     *
     * @return  \Generator
     */
    private function accept(SocketServerInterface $server, $cryptoMethod)
    {
        while ($server->isOpen()) {
            try {
                (new Coroutine($this->process((yield $server->accept()), $cryptoMethod)))->done();
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
     * @param   \Icicle\Socket\Client\ClientInterface $client
     * @param   int $cryptoMethod
     *
     * @return  \Generator
     */
    private function process(SocketClientInterface $client, $cryptoMethod)
    {
        $count = 0;

        try {
            if (0 !== $cryptoMethod) {
                yield $client->enableCrypto($cryptoMethod, $this->timeout);
            }

            do {
                try {
                    $request = (yield $this->readRequest($client, $this->maxHeaderSize, $this->timeout));

                    ++$count;

                    /** @var \Icicle\Http\Message\ResponseInterface $response */
                    $response = (yield $this->createResponse($request, $client));

                } catch (TimeoutException $exception) { // Request timeout.
                    if (0 < $count) {
                        return; // Keep-alive timeout expired.
                    }
                    $response = (yield $this->createErrorResponse(408, $client));
                } catch (MessageHeaderSizeException $exception) { // Request header too large.
                    $response = (yield $this->createErrorResponse(431, $client));
                } catch (MessageBodySizeException $exception) { // Request body too large.
                    $response = (yield $this->createErrorResponse(413, $client));
                } catch (LengthRequiredException $exception) { // Required content length missing.
                    $response = (yield $this->createErrorResponse(411, $client));
                } catch (UnexpectedValueException $exception) { // Bad request.
                    $response = (yield $this->createErrorResponse(400, $client));
                }

                yield $client->write($this->encoder->encodeResponse($response));

                $stream = $response->getBody();
                if ($stream->isReadable() && (!isset($request) || $request->getMethod() !== 'HEAD')) {
                    yield $stream->pipe($client, false);
                }

                $connection = strtolower($response->getHeaderLine('Connection'));

                if (isset($request) && $connection === 'upgrade') {
                    yield $this->upgrade($request, $response, $client);
                    return;
                }
            } while (
                $this->allowPersistent
                && $connection === 'keep-alive'
                && $client->isReadable()
                && $client->isWritable()
            );
        } catch (SocketException $exception) {
            // Ignore socket exceptions from client hang-ups.
        } catch (StreamException $exception) {
            // Ignore stream exceptions from client read/write failures.
        } finally {
            $client->close();
        }
    }

    /**
     * @coroutine
     *
     * @param   \Icicle\Http\Message\RequestInterface $request
     * @param   \Icicle\Http\Message\ResponseInterface $response
     * @param   \Icicle\Socket\Client\ClientInterface $client
     *
     * @return  \Generator
     */
    private function upgrade(RequestInterface $request, ResponseInterface $response, SocketClientInterface $client)
    {
        if (null === $this->onUpgrade) {
            throw new LogicException('No callback given for upgrade responses.');
        }

        $onUpgrade = $this->onUpgrade;
        yield $onUpgrade($request, $response, $client);
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Client\ClientInterface $client
     *
     * @return \Generator
     */
    private function readRequest(SocketClientInterface $client)
    {
        $request = (yield $this->reader->readRequest($client, $this->maxHeaderSize, $this->timeout));

        yield $this->builder->buildIncomingRequest($request, $this->timeout);
    }

    /**
     * @coroutine
     *
     * @param   \Icicle\Http\Message\RequestInterface $request
     * @param   \Icicle\Socket\Client\ClientInterface $client
     *
     * @return  \Generator
     *
     * @resolve \Icicle\Http\Message|ResponseInterface
     */
    private function createResponse(RequestInterface $request, SocketClientInterface $client)
    {
        try {
            $onRequest = $this->onRequest;
            $response = (yield $onRequest($request, $client));

            if (!$response instanceof ResponseInterface) {
                throw new InvalidCallableException(
                    'An \Icicle\Http\Message\ResponseInterface object was not returned from the request callback.',
                    $this->onRequest
                );
            }
        } catch (Exception $exception) {
            $response = (yield $this->createDefaultErrorResponse(500, $exception));
        }

        yield $this->builder->buildOutgoingResponse(
            $response,
            $request,
            $this->timeout,
            $this->allowPersistent
        );
    }

    /**
     * @coroutine
     *
     * @param   int $code
     * @param   \Icicle\Socket\Client\ClientInterface $client
     *
     * @return  \Generator
     *
     * @resolve \Icicle\Http\Message|ResponseInterface
     */
    private function createErrorResponse($code, SocketClientInterface $client)
    {
        try {
            if (null === $this->onError) {
                $response = (yield $this->createDefaultErrorResponse($code));
            } else {
                $onError = $this->onError;
                $response = (yield $onError($code, $client));

                if (!$response instanceof ResponseInterface) {
                    throw new InvalidCallableException(
                        'An \Icicle\Http\Message\ResponseInterface object was not returned from the error callback.',
                        $this->onError
                    );
                }
            }
        } catch (Exception $exception) {
            $response = (yield $this->createDefaultErrorResponse(500, $exception));
        }

        yield $this->builder->buildOutgoingResponse($response, null, $this->timeout, false);
    }

    /**
     * @coroutine
     *
     * @param   int $code
     * @param   \Exception|null $exception
     *
     * @return  \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface
     */
    protected function createDefaultErrorResponse($code, Exception $exception = null)
    {
        yield new Response($code, [
            'Connection' => 'close',
            'Content-Length' => 0,
        ]);
    }
}