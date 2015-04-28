<?php
namespace Icicle\Http\Server;

interface ServerInterface
{
    const DEFAULT_ADDRESS = '127.0.0.1';

    /**
     * @param   int $port
     * @param   string $address
     * @param   mixed[] $options
     */
    public function listen($port, $address = self::DEFAULT_ADDRESS, array $options = null);

    /**
     * Determines if the server is open, accepting connections.
     *
     * @return  bool
     */
    public function isOpen();

    /**
     * Closes the server. No more connections will be served.
     */
    public function close();

    /**
     * @return  float|int
     */
    public function getTimeout();

    /**
     * @return  bool
     */
    public function allowPersistent();
}
