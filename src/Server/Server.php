<?php
namespace Icicle\Http\Server;

use Icicle\Socket\Server\DefaultServerFactory;
use Icicle\Stream\WritableStream;

class Server
{
    const DEFAULT_ADDRESS = '0.0.0.0';

    /**
     * @var \Icicle\Http\Server\Listener
     */
    private $listener;

    public function __construct(RequestHandler $handler, WritableStream $log = null, array $options = [])
    {
        $driver = new Http1Driver($handler, $log, $options);
        $this->listener = new Listener($driver, new DefaultServerFactory(), $options);
    }

    public function isOpen()
    {
        return $this->listener->isOpen();
    }

    public function close()
    {
        $this->listener->close();
    }

    public function listen($port, $address = self::DEFAULT_ADDRESS, array $options = [])
    {
        $this->listener->listen($port, $address, $options);
    }
}