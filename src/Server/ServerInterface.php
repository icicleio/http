<?php
namespace Icicle\Http\Server;

interface ServerInterface
{
    /**
     * @param int|string $port Port number or socket.
     */
    public function listen($port);

    /**
     * Determines if the server is open, accepting connections.
     *
     * @return bool
     */
    public function isOpen();

    /**
     * Closes the server. No more connections will be served.
     */
    public function close();

    /**
     * @return float|int
     */
    public function getTimeout();

    /**
     * @return bool
     */
    public function allowPersistent();
}
