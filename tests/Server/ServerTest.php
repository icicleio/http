<?php
namespace Icicle\Tests\Http\Server;

use Icicle\Awaitable\Exception\TimeoutException;
use Icicle\Http\Driver\Driver;
use Icicle\Http\Exception\InvalidValueException;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Exception\ParseException;
use Icicle\Http\Message\BasicRequest;
use Icicle\Http\Message\BasicResponse;
use Icicle\Http\Message\Response;
use Icicle\Http\Server\RequestHandler;
use Icicle\Http\Server\Server;
use Icicle\Log\Log;
use Icicle\Loop;
use Icicle\Socket\Server\Server as SocketServer;
use Icicle\Socket\Server\ServerFactory;
use Icicle\Socket\Socket;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Tests\Http\TestCase;

class ServerTest extends TestCase
{
    const ADDRESS = '127.0.0.1';
    const PORT = 8080;

    /**
     * @var \Icicle\Socket\Server\ServerFactory
     */
    private $factory;

    /**
     * @var \Icicle\Http\Driver\Driver
     */
    private $driver;

    /**
     * @var \Icicle\Socket\Server\Server
     */
    private $server;

    /**
     * @var \Icicle\Log\Log
     */
    private $log;

    public function setUp()
    {
        $this->server = $this->getMock(SocketServer::class);
        $this->server->method('isOpen')
            ->willReturnOnConsecutiveCalls(true, false);
        $this->server->method('accept')
            ->will($this->returnCallback(function () {
                yield $this->getMock(Socket::class);
            }));

        $this->factory = $this->getMock(ServerFactory::class);
        $this->factory->method('create')
            ->will($this->returnValue($this->server));

        $this->driver = $this->getMock(Driver::class);
        $this->driver->method('writeResponse')
            ->will($this->returnCallback(function () {
                yield 1;
            }));

        $this->log = $this->getMock(Log::class);
        $this->log->method('log')
            ->will($this->returnCallback(function () {
                yield true;
            }));
    }

    public function testIsOpen()
    {
        $server = new Server($this->getMock(RequestHandler::class), $this->log, $this->driver, $this->factory);

        $this->assertTrue($server->isOpen());
    }

    /**
     * @depends testIsOpen
     * @expectedException \Icicle\Http\Exception\ClosedError
     */
    public function testClose()
    {
        $this->server->expects($this->once())
            ->method('close');

        $server = new Server($this->getMock(RequestHandler::class), $this->log, $this->driver, $this->factory);

        $server->listen(self::PORT, self::ADDRESS);

        $server->close();

        $this->assertFalse($server->isOpen());

        $server->listen(self::PORT);
    }

    public function testSuccessfulRequest()
    {
        $response = new BasicResponse(Response::OK);

        $handler = $this->getMock(RequestHandler::class);
        $handler->expects($this->once())
            ->method('onRequest')
            ->will($this->returnValue($response));

        $this->driver->expects($this->once())
            ->method('readRequest')
            ->will($this->returnCallback(function () {
                yield new BasicRequest('GET', 'http://example.com');
            }));
        $this->driver->expects($this->once())
            ->method('buildResponse')
            ->with($this->identicalTo($response))
            ->will($this->returnCallback(function (Response $response) {
                yield $response;
            }));

        $server = new Server($handler, $this->log, $this->driver, $this->factory);

        $server->listen(self::PORT, self::ADDRESS);

        Loop\run();
    }

    /**
     * @return array
     */
    public function getFailedRequestReasons()
    {
        return [
            [new TimeoutException(), Response::REQUEST_TIMEOUT],
            [new ParseException(), Response::BAD_REQUEST],
            [new InvalidValueException(), Response::BAD_REQUEST],
            [new MessageException(Response::BAD_REQUEST, 'Bad request.'), Response::BAD_REQUEST],
            [new MessageException(Response::REQUEST_HEADER_TOO_LARGE, 'Bad request.'), Response::REQUEST_HEADER_TOO_LARGE],
            [new MessageException(Response::REQUEST_ENTITY_TOO_LARGE, 'Bad request.'), Response::REQUEST_ENTITY_TOO_LARGE],
        ];
    }

    /**
     * @dataProvider getFailedRequestReasons
     * @depends testSuccessfulRequest
     *
     * @param \Exception $exception
     * @param int $code
     */
    public function testFailedRequest(\Exception $exception, $code)
    {
        $response = new BasicResponse($code);

        $handler = $this->getMock(RequestHandler::class);
        $handler->expects($this->never())
            ->method('onRequest');
        $handler->expects($this->once())
            ->method('onError')
            ->with($code)
            ->will($this->returnValue($response));

        $this->driver->expects($this->once())
            ->method('readRequest')
            ->will($this->returnCallback(function () use ($exception) {
                throw $exception;
                yield; // Unreachable, but makes function a coroutine.
            }));

        $this->driver->expects($this->once())
            ->method('buildResponse')
            ->with($this->identicalTo($response))
            ->will($this->returnCallback(function (Response $response) {
                yield $response;
            }));

        $server = new Server($handler, $this->log, $this->driver, $this->factory);

        $server->listen(self::PORT, self::ADDRESS);

        Loop\run();
    }

    /**
     * @depends testSuccessfulRequest
     */
    public function testInvalidOnRequestHandler()
    {
        $value = 1;

        $handler = $this->getMock(RequestHandler::class);
        $handler->expects($this->once())
            ->method('onRequest')
            ->will($this->returnValue($value));
        $handler->expects($this->never())
            ->method('onError');

        $this->driver->expects($this->once())
            ->method('readRequest')
            ->will($this->returnCallback(function () {
                yield new BasicRequest('GET', 'http://example.com');
            }));

        $this->driver->expects($this->once())
            ->method('buildResponse')
            ->will($this->returnCallback(function (Response $response) use (&$result) {
                $result = $response;
                yield $response;
            }));

        $server = new Server($handler, $this->log, $this->driver, $this->factory);

        $server->listen(self::PORT, self::ADDRESS);

        Loop\run();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(Response::INTERNAL_SERVER_ERROR, $result->getStatusCode());
    }

    /**
     * @depends testFailedRequest
     */
    public function testInvalidOnErrorHandler()
    {
        $value = 1;

        $handler = $this->getMock(RequestHandler::class);
        $handler->expects($this->never())
            ->method('onRequest');
        $handler->expects($this->once())
            ->method('onError')
            ->will($this->returnValue($value));

        $this->driver->expects($this->once())
            ->method('readRequest')
            ->will($this->returnCallback(function () {
                throw new MessageException(400, 'Bad request.');
                yield; // Unreachable, but makes function a coroutine.
            }));

        $this->driver->expects($this->once())
            ->method('buildResponse')
            ->will($this->returnCallback(function (Response $response) use (&$result) {
                $result = $response;
                yield $response;
            }));

        $server = new Server($handler, $this->log, $this->driver, $this->factory);

        $server->listen(self::PORT, self::ADDRESS);

        Loop\run();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(Response::INTERNAL_SERVER_ERROR, $result->getStatusCode());
    }

    public function testSocketFailure()
    {
        $response = new BasicResponse(Response::OK);

        $handler = $this->getMock(RequestHandler::class);
        $handler->expects($this->never())
            ->method('onRequest');

        $this->driver->expects($this->once())
            ->method('readRequest')
            ->will($this->returnCallback(function () {
                throw new UnreadableException();
                yield; // Unreachable, but makes function a coroutine.
            }));

        $this->driver->expects($this->never())
            ->method('buildResponse');
        $this->driver->expects($this->never())
            ->method('writeResponse');

        $server = new Server($handler, $this->log, $this->driver, $this->factory);

        $server->listen(self::PORT, self::ADDRESS);

        Loop\run();
    }

    /**
     * @depends testSuccessfulRequest
     */
    public function testKeepAlive()
    {
        $response1 = new BasicResponse(Response::OK, [
            'Connection' => 'keep-alive'
        ]);

        $response2 = new BasicResponse(Response::OK, [
            'Connection' => 'close'
        ]);

        $handler = $this->getMock(RequestHandler::class);
        $handler->expects($this->exactly(2))
            ->method('onRequest')
            ->will($this->onConsecutiveCalls($response1, $response2));

        $this->driver->expects($this->exactly(2))
            ->method('readRequest')
            ->will($this->returnCallback(function () {
                yield new BasicRequest('GET', 'http://example.com', [
                    'Connection' => 'keep-alive'
                ]);
            }));
        $this->driver->expects($this->exactly(2))
            ->method('buildResponse')
            ->will($this->returnCallback(function (Response $response) {
                yield $response;
            }));

        $server = new Server($handler, $this->log, $this->driver, $this->factory);

        $server->listen(self::PORT, self::ADDRESS);

        Loop\run();
    }

    /**
     * @depends testKeepAlive
     */
    public function testKeepAliveTimeout()
    {
        $response = new BasicResponse(Response::OK, [
            'Connection' => 'keep-alive'
        ]);

        $handler = $this->getMock(RequestHandler::class);
        $handler->expects($this->once())
            ->method('onRequest')
            ->will($this->returnValue($response));

        $this->driver->expects($this->exactly(2))
            ->method('readRequest')
            ->will($this->returnCallback(function () {
                static $i = 0;
                if (0 === $i++) {
                    yield new BasicRequest('GET', 'http://example.com', [
                        'Connection' => 'keep-alive'
                    ]);
                    return;
                }

                throw new TimeoutException();
            }));
        $this->driver->expects($this->once())
            ->method('buildResponse')
            ->will($this->returnCallback(function (Response $response) {
                yield $response;
            }));

        $server = new Server($handler, $this->log, $this->driver, $this->factory);

        $server->listen(self::PORT, self::ADDRESS);

        Loop\run();
    }
}
