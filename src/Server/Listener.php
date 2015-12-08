<?php
namespace Icicle\Http\Server;

use Exception;
use Icicle\Coroutine\Coroutine;
use Icicle\Socket\Server\ServerFactory;
use Icicle\Socket\Server\Server as SocketServer;

class Listener
{
    const DEFAULT_CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLS_SERVER;
    const DEFAULT_TIMEOUT = 15;

    /**
     * @var \Icicle\Socket\Server\ServerFactory
     */
    private $factory;

    /**
     * @var \Icicle\Socket\Server\Server[]
     */
    private $servers = [];

    /**
     * @var bool
     */
    private $open = true;

    /**
     * @param \Icicle\Http\Server\Driver $driver
     * @param \Icicle\Socket\Server\ServerFactory $factory
     * @param mixed[] $options
     */
    public function __construct(
        Driver $driver,
        ServerFactory $factory,
        array $options = []
    ) {
        $this->driver = $driver;
        $this->factory = $factory;
    }

    /**
     * @return bool
     */
    public function isOpen()
    {
        return $this->open;
    }

    /**
     * Closes all listening servers.
     */
    public function close()
    {
        $this->open = false;

        foreach ($this->servers as $server) {
            $server->close();
        }
    }

    /**
     * @param string $address
     * @param int $port
     * @param mixed[] $options
     *
     * @throws \Icicle\Http\Exception\Error If the server has been closed.
     *
     * @see \Icicle\Socket\Server\ServerFactory::create() Options are similar to this method with the
     *     addition of the crypto_method option.
     */
    public function listen($port, $address, array $options = [])
    {
        if (!$this->open) {
            throw new Error('The server has been closed.');
        }

        $cryptoMethod = isset($options['crypto_method'])
            ? (int) $options['crypto_method']
            : (isset($options['pem']) ? self::DEFAULT_CRYPTO_METHOD : 0);
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $allowPersistent = isset($options['allow_persistent']) ? (bool) $options['allow_persistent'] : true;

        $server = $this->factory->create($address, $port, $options);

        $this->servers[] = $server;

        $coroutine = new Coroutine($this->accept($server, $cryptoMethod, $timeout, $allowPersistent));
        $coroutine->done();
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Server\Server $server
     * @param int $cryptoMethod
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     */
    private function accept(SocketServer $server, $cryptoMethod, $timeout, $allowPersistent)
    {
        while ($server->isOpen()) {
            try {
                $coroutine = new Coroutine(
                    $this->driver->process((yield $server->accept()), $cryptoMethod, $timeout, $allowPersistent)
                );
                $coroutine->done();
            } catch (Exception $exception) {
                if ($this->open) {
                    $this->close();
                    throw $exception;
                }
            }
        }
    }
}
