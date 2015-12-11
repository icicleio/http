<?php
namespace Icicle\Http\Client;

use Icicle\Http\Driver\Driver;
use Icicle\Http\Message\Request;
use Icicle\Socket\Socket;

class Requester
{
    const DEFAULT_CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLS_CLIENT;
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
     * {@inheritdoc}
     */
    public function request(Socket $socket, Request $request, array $options = [])
    {
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;

        $uri = $request->getUri();

        if ($uri->getScheme() === 'https' && !$socket->isCryptoEnabled()) {
            $cryptoMethod = isset($options['crypto_method'])
                ? (int) $options['crypto_method']
                : self::DEFAULT_CRYPTO_METHOD;

            yield $socket->enableCrypto($cryptoMethod, $timeout);
        } elseif ($uri->getScheme() === 'http' && $socket->isCryptoEnabled()) {
            throw new \Exception('Crypto is enabled on the socket when making an http request.');
        }

        $request = (yield $this->driver->buildRequest($socket, $request, $timeout, $allowPersistent));

        yield $this->driver->writeRequest($socket, $request, $timeout);

        yield $this->driver->readResponse($socket, $timeout);
    }
}
