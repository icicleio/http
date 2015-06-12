<?php
namespace Icicle\Tests\Http\Server;

use Icicle\Http\Builder\BuilderInterface;
use Icicle\Http\Encoder\EncoderInterface;
use Icicle\Http\Exception\InvalidCallableException;
use Icicle\Http\Exception\UnexpectedValueException;
use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\ResponseInterface;
use Icicle\Http\Reader\ReaderInterface;
use Icicle\Http\Server\Server;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Socket\Server\ServerFactoryInterface;
use Icicle\Socket\Server\ServerInterface;
use Icicle\Stream\ReadableStreamInterface;
use Icicle\Tests\Http\TestCase;
use Mockery;
use Symfony\Component\Yaml;

class ServerTest extends TestCase
{
    /**
     * @param \Icicle\Socket\Server\ServerInterface
     *
     * @return \Icicle\Socket\Server\ServerFactoryInterface
     */
    public function createFactory(ServerInterface $server)
    {
        $mock = Mockery::mock(ServerFactoryInterface::class);

        $mock->shouldReceive('create')
            ->andReturn($server);

        return $mock;
    }

    /**
     * @param \Icicle\Socket\Client\ClientInterface
     *
     * @return \Icicle\Socket\Server\ServerInterface
     */
    public function createSocketServer(ClientInterface $client)
    {
        $mock = Mockery::mock(ServerInterface::class);

        $mock->shouldReceive('isOpen')
            ->andReturnValues([true, false]);

        $mock->shouldReceive('close');

        $mock->shouldReceive('accept')
            ->andReturn(Promise\resolve($client));

        return $mock;
    }

    /**
     * @return \Icicle\Socket\Client\ClientInterface
     */
    public function createSocketClient()
    {
        $mock = Mockery::mock(ClientInterface::class);

        $mock->shouldReceive('close');

        $mock->shouldReceive('write')
            ->with(Mockery::mustBe('Encoded response.'));

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
            $options['factory'] = $this->createFactory($this->createSocketServer($this->createSocketClient()));
        }

        return new Server($onRequest, $onInvalidRequest, $onError, $onUpgrade, $options);
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
     * @expectedException \Icicle\Http\Exception\LogicException
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
                ->with(Mockery::type(ClientInterface::class), Mockery::type('bool'))
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
            $this->assertInstanceOf(InvalidCallableException::class, $exception);
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
            ['Icicle\Http\Exception\MessageHeaderSizeException', 431],
            ['Icicle\Http\Exception\MessageBodySizeException', 413],
            ['Icicle\Http\Exception\LengthRequiredException', 411],
            ['Icicle\Http\Exception\UnexpectedValueException', 400],
            ['Icicle\Socket\Exception\TimeoutException', 408],
        ];
    }

    /**
     * @dataProvider invalidRequestExceptions
     * @param string $exceptionName
     * @param int $statusCode
     */
    public function testInvalidRequest($exceptionName, $statusCode)
    {
        $reader = Mockery::mock(ReaderInterface::class);
        $reader->shouldReceive('readRequest')
            ->andThrow($exceptionName);

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
            ->andThrow(UnexpectedValueException::class);

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
            $this->assertInstanceOf(InvalidCallableException::class, $exception);
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
            ->andThrow(UnexpectedValueException::class);

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
            ->andThrow(UnexpectedValueException::class);

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
