<?php
namespace Icicle\Tests\Http\Server;

use Icicle\Http\Server\Server;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Socket\Server\ServerInterface;
use Icicle\Tests\Http\TestCase;
use Mockery;
use Symfony\Component\Yaml;

class ServerTest extends TestCase
{
    protected $factory;

    protected $client;

    protected $reader;

    protected $encoder;

    protected $builder;

    protected $server;

    public function setUp()
    {
        $this->client = $this->createSocketClient();
        $this->server = $this->createSocketServer($this->client);
        $this->factory = $this->createFactory($this->server);
        $this->reader = $this->createReader();
        $this->builder = $this->createBuilder();
        $this->encoder = $this->createEncoder();
    }

    /**
     * @param   \Icicle\Socket\Server\ServerInterface
     *
     * @return  \Icicle\Socket\Server\ServerFactoryInterface
     */
    public function createFactory(ServerInterface $server)
    {
        $mock = Mockery::mock('Icicle\Socket\Server\ServerFactoryInterface');

        $mock->shouldReceive('create')
            ->andReturn($server);

        return $mock;
    }

    /**
     * @param   \Icicle\Socket\Client\ClientInterface
     *
     * @return  \Icicle\Socket\Server\ServerInterface
     */
    public function createSocketServer(ClientInterface $client)
    {
        $mock = Mockery::mock('Icicle\Socket\Server\ServerInterface');

        $mock->shouldReceive('isOpen')
            ->andReturnValues([true, false]);

        $mock->shouldReceive('close');

        $mock->shouldReceive('accept')
            ->andReturn(Promise\resolve($client));

        return $mock;
    }

    /**
     * @return  \Icicle\Socket\Client\ClientInterface
     */
    public function createSocketClient()
    {
        $mock = Mockery::mock('Icicle\Socket\Client\ClientInterface');

        $mock->shouldReceive('close');

        $mock->shouldReceive('write');

        return $mock;
    }

    /**
     * @return  \Icicle\Http\Reader\ReaderInterface
     */
    public function createReader()
    {
        $mock = Mockery::mock('Icicle\Http\Reader\ReaderInterface');

        $mock->shouldReceive('readRequest')
            ->andReturnUsing(function () {
                return $this->createRequest();
            });

        return $mock;
    }

    /**
     * @return  \Icicle\Http\Encoder\EncoderInterface
     */
    public function createEncoder()
    {
        $mock = Mockery::mock('Icicle\Http\Encoder\EncoderInterface');

        $mock->shouldReceive('encodeResponse')
            ->andReturn('Encoded response.');
    }

    /**
     * @return  \Icicle\Http\Builder\BuilderInterface
     */
    public function createBuilder()
    {
        $mock = Mockery::mock('Icicle\Http\Builder\BuilderInterface');

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
     * @param   callable $onRequest
     * @param   callable|null $onError
     * @param   mixed[]|null $options
     *
     * @return  \Icicle\Http\Server\Server
     */
    public function createServer(callable $onRequest, callable $onError = null, array $options = null)
    {
        return new Server($onRequest, $onError, $options, $this->reader, $this->builder, $this->encoder, $this->factory);
    }

    /**
     * @return  \Icicle\Http\Message\RequestInterface
     */
    public function createRequest()
    {
        return Mockery::mock('Icicle\Http\Message\RequestInterface');
    }

    /**
     * @return  \Icicle\Http\Message\ResponseInterface
     */
    public function createResponse()
    {
        return Mockery::mock('Icicle\Http\Message\ResponseInterface');
    }

    public function testOnRequest()
    {
        $response = $this->createResponse();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Http\Message\RequestInterface'))
            ->will($this->returnValue($response));

        $server = $this->createServer($callback, $this->createCallback(0));

        $server->listen(8080);

        Loop\run();
    }
}
