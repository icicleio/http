<?php
namespace Icicle\Tests\Http\Server;

use Icicle\Http\Builder\BuilderInterface;
use Icicle\Http\Encoder\EncoderInterface;
use Icicle\Http\Exception\InvalidCallableError;
use Icicle\Http\Exception\InvalidValueException;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Exception\ParseException;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\ResponseInterface;
use Icicle\Http\Reader\ReaderInterface;
use Icicle\Http\Server\Server;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Socket\Server\ServerFactoryInterface;
use Icicle\Socket\Server\ServerInterface;
use Icicle\Socket\SocketInterface;
use Icicle\Stream\ReadableStreamInterface;
use Icicle\Tests\Http\TestCase;
use Mockery;
use Symfony\Component\Yaml;

class ServerTest extends TestCase
{
    /**
     * @param \Icicle\Socket\Server\ServerInterface $server
     *
     * @return \Icicle\Socket\Server\ServerFactoryInterface
     */
    public function createFactory(ServerInterface $server = null)
    {
        $mock = Mockery::mock(ServerFactoryInterface::class);

        $mock->shouldReceive('create')
            ->andReturnUsing(function () use ($server) {
                return $server ?: $this->createSocketServer();
            });

        return $mock;
    }

    /**
     * @param \Icicle\Socket\SocketInterface $socket
     *
     * @return \Icicle\Socket\Server\ServerInterface
     */
    public function createSocketServer(SocketInterface $socket = null)
    {
        $mock = Mockery::mock(ServerInterface::class);

        $mock->shouldReceive('isOpen')
            ->andReturnValues([true, false]);

        $mock->shouldReceive('close');

        $mock->shouldReceive('accept')
            ->andReturnUsing(function () use ($socket) {
                yield $socket ?: $this->createSocketClient();
            });

        return $mock;
    }

    /**
     * @return \Icicle\Socket\SocketInterface
     */
    public function createSocketClient()
    {
        $mock = Mockery::mock(SocketInterface::class);

        $mock->shouldReceive('close');

        $mock->shouldReceive('isReadable')
            ->andReturn(true);

        $mock->shouldReceive('isWritable')
            ->andReturn(true);

        $mock->shouldReceive('write')
            ->andReturnUsing(function ($data) {
                yield strlen($data);
            });

        return $mock;
    }

    /**
     * @return \Icicle\Http\Reader\ReaderInterface
     */
    public function createReader()
    {
        $mock = Mockery::mock(ReaderInterface::class);

        $generator = function () {
            yield $this->createRequest();
        };

        $mock->shouldReceive('readRequest')
            ->andReturn($generator());

        return $mock;
    }

    /**
     * @return \Icicle\Http\Encoder\EncoderInterface
     */
    public function createEncoder()
    {
        $mock = Mockery::mock(EncoderInterface::class);

        $mock->shouldReceive('encodeResponse')
            ->andReturn('Encoded response.');

        return $mock;
    }

    /**
     * @return \Icicle\Http\Builder\BuilderInterface
     */
    public function createBuilder()
    {
        $mock = Mockery::mock(BuilderInterface::class);

        $mock->shouldReceive('buildIncomingRequest')
            ->andReturnUsing(function ($request) {
                return $request;
            });

        $mock->shouldReceive('buildOutgoingResponse')
            ->andReturnUsing(function ($response) {
                return $response;
            });

        return $mock;
    }

    /**
     * @param callable $onRequest
     * @param callable|null $onInvalidRequest
     * @param callable|null $onError
     * @param callable|null $onUpgrade
     * @param mixed[]|null $options
     *
     * @return \Icicle\Http\Server\Server
     */
    public function createServer(
        callable $onRequest,
        callable $onInvalidRequest = null,
        callable $onError = null,
        callable $onUpgrade = null,
        array $options = null
    ) {
        if (!isset($options['reader'])) {
            $options['reader'] = $this->createReader();
        }

        if (!isset($options['builder'])) {
            $options['builder'] = $this->createBuilder();
        }

        if (!isset($options['encoder'])) {
            $options['encoder'] = $this->createEncoder();
        }

        if (!isset($options['factory'])) {
            $options['factory'] = $this->createFactory();
        }

        $server = new Server($onRequest, $options);

        $server->setInvalidRequestHandler($onInvalidRequest);
        $server->setErrorHandler($onError);
        $server->setUpgradeHandler($onUpgrade);

        return $server;
    }

    /**
     * @param string $method
     *
     * @return \Icicle\Http\Message\RequestInterface
     */
    public function createRequest($method = 'GET')
    {
        $mock = Mockery::mock(RequestInterface::class);

        $mock->shouldReceive('getMethod')
            ->andReturn($method);

        return $mock;
    }

    /**
     * @return \Icicle\Http\Message\ResponseInterface
     */
    public function createResponse()
    {
        $mock = Mockery::mock(ResponseInterface::class);

        $mock->shouldReceive('getBody')
            ->andReturn(Mockery::mock(ReadableStreamInterface::class));

        return $mock;
    }

    public function testClose()
    {
        $server = $this->getMock(ServerInterface::class);
        $server->expects($this->exactly(2))
            ->method('close');

        $factory = $this->createFactory($server);

        $server = $this->createServer(
            $this->createCallback(0),
            $this->createCallback(0),
            $this->createCallback(0),
            $this->createCallback(0),
            ['factory' => $factory]
        );

        $this->assertTrue($server->isOpen());

        $server->listen(8080);
        $server->listen(8888);

        $server->close();

        $this->assertFalse($server->isOpen());

        Loop\run();
    }

    /**
     * @depends testClose
     * @expectedException \Icicle\Http\Exception\Error
     */
    public function testListenAfterClose()
    {
        $server = $this->createServer($this->createCallback(0), $this->createCallback(0));

        $server->close();

        $server->listen(8080);
    }

    public function testOnRequest()
    {
        $callback = function ($request) {
            $this->assertInstanceOf(RequestInterface::class, $request);

            $response = $this->createResponse();

            $response->shouldReceive('getHeaderLine')
                ->with(Mockery::mustBe('Connection'))
                ->andReturn('close');

            $response->getBody()
                ->shouldReceive('isReadable')
                ->andReturn(true);

            $response->getBody()
                ->shouldReceive('pipe')
                ->with(Mockery::type(SocketInterface::class), Mockery::type('bool'))
                ->andReturn(Promise\resolve(0));

            return $response;
        };

        $server = $this->createServer($callback, $this->createCallback(0));

        $server->listen(8080);

        Loop\run();
    }

    /**
     * @depends testOnRequest
     */
    public function testOnRequestReturnsNonResponse()
    {
        $encoder = Mockery::mock(EncoderInterface::class);
        $encoder->shouldReceive('encodeResponse')
            ->andReturnUsing(function ($response) {
                $this->assertInstanceOf(ResponseInterface::class, $response);
                $this->assertSame(500, $response->getStatusCode());
                return 'Encoded response.';
            });

        $onRequest = function ($request) {
            return $request;
        };

        $onError = function (\Exception $exception) use ($onRequest) {
            $this->assertInstanceOf(InvalidCallableError::class, $exception);
            $this->assertSame($onRequest, $exception->getCallable());
        };

        $server = $this->createServer(
            $onRequest,
            $this->createCallback(0),
            $onError,
            $this->createCallback(0),
            ['encoder' => $encoder]
        );

        $server->listen(8080);

        Loop\run();
    }

    /**
     * @depends testOnRequest
     */
    public function testOnRequestThrowsException()
    {
        $encoder = Mockery::mock(EncoderInterface::class);
        $encoder->shouldReceive('encodeResponse')
            ->andReturnUsing(function ($response) {
                $this->assertInstanceOf(ResponseInterface::class, $response);
                $this->assertSame(500, $response->getStatusCode());
                return 'Encoded response.';
            });

        $exception = new \Exception();

        $onRequest = function ($request) use ($exception) {
            throw $exception;
        };

        $onError = function (\Exception $e) use ($exception) {
            $this->assertSame($exception, $e);
        };

        $server = $this->createServer(
            $onRequest,
            $this->createCallback(0),
            $onError,
            $this->createCallback(0),
            ['encoder' => $encoder]
        );

        $server->listen(8080);

        Loop\run();
    }

    public function testCryptoMethod()
    {
        $cryptoMethod = -1;

        $client = $this->createSocketClient();
        $client->shouldReceive('enableCrypto')
            ->with(Mockery::mustBe($cryptoMethod), Mockery::any())
            ->andReturn($client);

        $factory = $this->createFactory($this->createSocketServer($client));

        $server = $this->createServer(
            $this->createCallback(1),
            $this->createCallback(0),
            $this->createCallback(1),
            $this->createCallback(0),
            ['factory' => $factory]
        );

        $server->listen(8080, Server::DEFAULT_ADDRESS, ['crypto_method' => $cryptoMethod]);

        Loop\run();
    }

    public function invalidRequestExceptions()
    {
        return [
            [new MessageException(431, 'Body too long'), 431],
            [new MessageException(413, 'Headers too long'), 413],
            [new MessageException(411, 'Length required'), 411],
            [new MessageException(400, 'Bad request'), 400],
            [new TimeoutException('Reading timed out'), 408],
            [new ParseException('Parse error in message'), 400],
            [new InvalidValueException('Invalid value in message'), 400],
        ];
    }

    /**
     * @dataProvider invalidRequestExceptions
     * @param \Exception $exception
     * @param int $statusCode
     */
    public function testInvalidRequest(\Exception $exception, $statusCode)
    {
        $reader = Mockery::mock(ReaderInterface::class);
        $reader->shouldReceive('readRequest')
            ->andThrow($exception);

        $callback = function ($code) use ($statusCode) {
            $this->assertSame($statusCode, $code);

            $response = $this->createResponse();

            $response->shouldReceive('getHeaderLine')
                ->with(Mockery::mustBe('Connection'))
                ->andReturn('close');

            $response->getBody()
                ->shouldReceive('isReadable')
                ->andReturn(false);

            return $response;
        };

        $server = $this->createServer(
            $this->createCallback(0),
            $callback,
            $this->createCallback(0),
            $this->createCallback(0),
            ['reader' => $reader]
        );

        $server->listen(8080);

        Loop\run();
    }

    /**
     * @depends testInvalidRequest
     */
    public function testOnInvalidRequestReturnsNonResponse()
    {
        $reader = Mockery::mock(ReaderInterface::class);
        $reader->shouldReceive('readRequest')
            ->andThrow(new MessageException(400, 'Reason'));

        $encoder = Mockery::mock(EncoderInterface::class);
        $encoder->shouldReceive('encodeResponse')
            ->andReturnUsing(function ($response) {
                $this->assertInstanceOf(ResponseInterface::class, $response);
                $this->assertSame(500, $response->getStatusCode());
                return 'Encoded response.';
            });

        $onInvalidRequest = function ($code) {
            return $code;
        };

        $onError = function (\Exception $exception) use ($onInvalidRequest) {
            if (!$exception instanceof InvalidCallableError) {
                throw $exception;
            }

            $this->assertInstanceOf(InvalidCallableError::class, $exception);
            $this->assertSame($onInvalidRequest, $exception->getCallable());
        };

        $server = $this->createServer(
            $this->createCallback(0),
            $onInvalidRequest,
            $onError,
            $this->createCallback(0),
            ['reader' => $reader, 'encoder' => $encoder]
        );

        $server->listen(8080);

        Loop\run();
    }

    /**
     * @depends testInvalidRequest
     */
    public function testOnInvalidRequestThrowsException()
    {
        $reader = Mockery::mock(ReaderInterface::class);
        $reader->shouldReceive('readRequest')
            ->andThrow(new MessageException(400, 'Reason'));

        $encoder = Mockery::mock(EncoderInterface::class);
        $encoder->shouldReceive('encodeResponse')
            ->andReturnUsing(function ($response) {
                $this->assertInstanceOf(ResponseInterface::class, $response);
                $this->assertSame(500, $response->getStatusCode());
                return 'Encoded response.';
            });

        $exception = new \Exception();

        $onInvalidRequest = function ($request) use ($exception) {
            throw $exception;
        };

        $onError = function (\Exception $e) use ($exception) {
            $this->assertSame($exception, $e);
        };

        $server = $this->createServer(
            $this->createCallback(0),
            $onInvalidRequest,
            $onError,
            $this->createCallback(0),
            ['reader' => $reader, 'encoder' => $encoder]
        );

        $server->listen(8080);

        Loop\run();
    }

    /**
     * @depends testInvalidRequest
     */
    public function testInvalidRequestWithNoOnErrorCallback()
    {
        $reader = Mockery::mock(ReaderInterface::class);
        $reader->shouldReceive('readRequest')
            ->andThrow(new MessageException(400, 'Reason'));

        $encoder = Mockery::mock(EncoderInterface::class);
        $encoder->shouldReceive('encodeResponse')
            ->andReturnUsing(function ($response) {
                $this->assertInstanceOf(ResponseInterface::class, $response);
                $this->assertSame(400, $response->getStatusCode());
                return 'Encoded response.';
            });

        $server = $this->createServer(
            $this->createCallback(0),
            null,
            $this->createCallback(0),
            $this->createCallback(0),
            ['reader' => $reader, 'encoder' => $encoder]
        );

        $server->listen(8080);

        Loop\run();
    }
}
