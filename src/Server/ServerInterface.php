<?php
namespace Icicle\Http\Server;

interface ServerInterface
{
    const DEFAULT_ADDRESS = '0.0.0.0';
    const DEFAULT_TIMEOUT = 15;
    const DEFAULT_CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLS_SERVER;

    /**
     * @param int $port Port number.
     * @param string $address
     * @param mixed[] $options
     */
    public function listen($port, $address = self::DEFAULT_ADDRESS, array $options = []);

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
}
