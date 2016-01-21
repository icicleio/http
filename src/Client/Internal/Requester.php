<?php
namespace Icicle\Http\Client\Internal;

use Icicle\Http\Driver\Driver;
use Icicle\Http\Message\Request;
use Icicle\Socket\Socket;

class Requester
{
    const DEFAULT_TIMEOUT = 15;

    /**
     * @var \Icicle\Http\Driver\Driver
     */
    private $driver;

    /**
     * @param \Icicle\Http\Driver\Driver $driver
     */
    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Request $request
     * @param mixed[] $options
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function send(Socket $socket, Request $request, array $options = [])
    {
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;

        $request = (yield $this->driver->buildRequest($request, $timeout, $allowPersistent));

        yield $this->driver->writeRequest($socket, $request, $timeout);

        yield $this->driver->readResponse($socket, $timeout);
    }
}
