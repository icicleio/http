<?php
namespace Icicle\Http\Server;

use Icicle\Http\Driver\Http1Driver;
use Icicle\Socket\Server\DefaultServerFactory;
use Icicle\Stream;
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
        $driver = new Http1Driver($options);
        $this->listener = new Listener($driver, $handler, $log ?: Stream\stderr(), new DefaultServerFactory());
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