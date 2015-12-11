<?php
namespace Icicle\Http\Client;

use Icicle\Http\Driver\Driver;
use Icicle\Http\Message\Request;
use Icicle\Stream;
use Icicle\Socket\Socket;

class Requester
{
    const DEFAULT_TIMEOUT = 15;

    /**
     * @var \Icicle\Http\Driver\Driver
     */
    private $driver;

    /**
     * @param mixed[] $options
     */
    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * {@inheritdoc}
     */
    public function request(Socket $socket, Request $request, array $options = [])
    {
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;

        $request = (yield $this->driver->buildRequest($socket, $request, $timeout, $allowPersistent));

        yield $this->driver->writeRequest($socket, $request, $timeout);

        yield $this->driver->readResponse($socket, $timeout);
    }
}