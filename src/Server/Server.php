<?php
namespace Icicle\Http\Server;

use Exception;
use Icicle\Coroutine\Coroutine;
use Icicle\Http\Builder\Builder;
use Icicle\Http\Builder\BuilderInterface;
use Icicle\Http\Encoder\Encoder;
use Icicle\Http\Encoder\EncoderInterface;
use Icicle\Http\Exception\InvalidArgumentException;
use Icicle\Http\Exception\InvalidCallableException;
use Icicle\Http\Exception\LogicException;
use Icicle\Http\Exception\MessageBodySizeException;
use Icicle\Http\Exception\MessageHeaderSizeException;
use Icicle\Http\Exception\UnexpectedValueException;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\Response;
use Icicle\Http\Message\ResponseInterface;
use Icicle\Http\Parser\Parser;
use Icicle\Http\Parser\ParserInterface;
use Icicle\Socket\Client\ClientInterface as SocketClientInterface;
use Icicle\Socket\Exception\AcceptException;
use Icicle\Socket\Exception\ExceptionInterface as SocketException;
use Icicle\Socket\Exception\FailureException;
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
     * @var callable
     */
    private $onError;

    /**
     * @var \Icicle\Http\Encoder\EncoderInterface
     */
    private $encoder;

    /**
     * @var \Icicle\Http\Parser\ParserInterface
     */
    private $parser;

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
     * @param   callable $onError
     * @param   mixed[] $options
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If one of the options given is of an incorrect type.
     */
    public function __construct(callable $onRequest, callable $onError = null, array $options = null)
    {
        $this->timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $this->allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;
        $this->maxHeaderSize = isset($options['max_header_size'])
            ? (int) $options['max_header_size']
            : self::DEFAULT_MAX_HEADER_SIZE;

        $this->factory = isset($options['factory']) ? $options['factory'] : new ServerFactory();
        if (!$this->factory instanceof ServerFactoryInterface) {
            throw new InvalidArgumentException(
                'Server factory must be an instance of Icicle\Socket\Server\ServerFactoryInterface'
            );
        }

        $this->parser = isset($options['parser']) ? $options['parser'] : new Parser();
        if (!$this->parser instanceof ParserInterface) {
            throw new InvalidArgumentException(
                'Message parser must be an instance of Icicle\Http\Parser\ParserInterface'
            );
        }

        $this->encoder = isset($options['encoder']) ? $options['encoder'] : new Encoder();
        if (!$this->encoder instanceof EncoderInterface) {
            throw new InvalidArgumentException(
                'Message encoder must be an instance of Icicle\Http\Encoder\EncoderInterface'
            );
        }

        $this->builder = isset($options['builder']) ? $options['builder'] : new Builder();
        if (!$this->builder instanceof BuilderInterface) {
            throw new InvalidArgumentException(
                'Message builder must be an instance of Icicle\Http\Builder\BuilderInterface'
            );
        }

        $this->onRequest = $onRequest;
        $this->onError = $onError;
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
     * @see     \Icicle\Socket\Server\ServerFactoryInterface::create()
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
    protected function accept(SocketServerInterface $server, $cryptoMethod)
    {
        while ($server->isOpen()) {
            try {
                $client = (yield $server->accept());

                (new Coroutine($this->process($client, $cryptoMethod)))->done();
            } catch (AcceptException $exception) {
                // Ignore failed client accept.
            } catch (FailureException $exception) {
                // Ignore failed client connections.
            } catch (Exception $exception) {
                if ($this->isOpen()) {
                    $this->close();
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
    protected function process(SocketClientInterface $client, $cryptoMethod)
    {
        $count = 0;
        $upgrade = false;

        try {
            if (0 !== $cryptoMethod) {
                yield $client->enableCrypto($cryptoMethod, $this->timeout);
            }

            do {
                try {
                    $request = $this->parser->parseRequest(
                        (yield $this->parser->readMessage($client, $this->maxHeaderSize, $this->timeout)),
                        $client
                    );

                    $request = $this->builder->buildIncomingRequest($request, $this->getTimeout());

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
                } catch (UnexpectedValueException $exception) { // Bad request.
                    $response = (yield $this->createErrorResponse(400, $client));
                }

                yield $client->write($this->encoder->encodeResponse($response));

                $stream = $response->getBody();

                if ($stream->isReadable() && (!isset($request) || $request->getMethod() !== 'HEAD')) {
                    yield $stream->pipe($client, false);
                }

                $connection = strtolower($response->getHeaderLine('Connection'));
                $upgrade = $connection === 'upgrade';
                $keepAlive = !$upgrade && $this->allowPersistent() && $connection === 'keep-alive';
            } while ($keepAlive && $client->isReadable() && $client->isWritable());
        } catch (SocketException $exception) {
            // Ignore socket exceptions from client hang-ups.
        } catch (StreamException $exception) {
            // Ignore stream exceptions from client read/write failures.
        } catch (Exception $exception) {
            $this->close();
        } finally {
            if (!$upgrade) { // Only close if connection was not upgraded.
                $client->close();
            }
        }
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
    protected function createResponse(RequestInterface $request, SocketClientInterface $client)
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

            yield $this->builder->buildOutgoingResponse(
                $response,
                $request,
                $this->getTimeout(),
                $this->allowPersistent()
            );
        } catch (Exception $exception) {
            yield $this->createDefaultErrorResponse(500, $exception);
        }
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
    protected function createErrorResponse($code, SocketClientInterface $client)
    {
        try {
            if (null === $this->onError) {
                yield $this->createDefaultErrorResponse($code);
                return;
            }

            $onError = $this->onError;
            $response = (yield $onError($code, $client));

            if (!$response instanceof ResponseInterface) {
                throw new InvalidCallableException(
                    'An \Icicle\Http\Message\ResponseInterface object was not returned from the error callback.',
                    $this->onError
                );
            }

            yield $this->builder->buildOutgoingResponse($response, null, $this->getTimeout(), false);
        } catch (Exception $exception) {
            yield $this->createDefaultErrorResponse(500, $exception);
        }
    }

    /**
     * @param   int $code
     * @param   \Exception|null $exception
     *
     * @return  \Icicle\Http\Message\ResponseInterface
     */
    protected function createDefaultErrorResponse($code, Exception $exception = null)
    {
        $headers = [
            'Connection' => 'close',
            'Content-Length' => '0',
        ];

        return new Response($code, $headers);
    }
}
