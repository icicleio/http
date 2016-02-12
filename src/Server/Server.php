<?php
namespace Icicle\Http\Server;

use Icicle\Http\Driver\Http1Driver;
use Icicle\Log as LogNS;
use Icicle\Log\Log;
use Icicle\Socket\Server\DefaultServerFactory;

final class Server
{
    const DEFAULT_ADDRESS = '*';

    /**
     * @var \Icicle\Http\Server\Internal\Listener
     */
    private $listener;

    /**
     * @param \Icicle\Http\Server\RequestHandler $handler
     * @param \Icicle\Log\Log|null $log
     * @param mixed[] $options
     */
    public function __construct(RequestHandler $handler, Log $log = null, array $options = [])
    {
        $this->listener = new Internal\Listener(
            new Http1Driver($options),
            $handler,
            $log ?: LogNS\log(),
            new DefaultServerFactory()
        );
    }

    /**
     * Determines if the server is still open.
     *
     * @return bool
     */
    public function isOpen()
    {
        return $this->listener->isOpen();
    }

    /**
     * Closes all listening ports.
     */
    public function close()
    {
        $this->listener->close();
    }

    /**
     * @param int $port Port to listen on.
     * @param string $address Use * to bind on all IPv4 and IPv6 addresses on the given port. Use 'localhost' to listen
     *     on 127.0.0.1 and [::1]. Use '0.0.0.0' to listen on all IPv4 addresses or '[::]' for all IPv6 addresses.
     *     Otherwise pass a specific IPv4 or IPv6 address.
     * @param mixed[] $options
     *
     * @throws \Icicle\Http\Exception\ClosedError If the server has been closed.
     * @throws \Icicle\Socket\Exception\FailureException If creating the server fails.
     */
    public function listen($port, $address = self::DEFAULT_ADDRESS, array $options = [])
    {
        switch ($address) {
            case '*':
                $this->listener->listen($port, '0.0.0.0', $options);
                $this->listener->listen($port, '[::]', $options);
                break;

            case 'localhost':
                $this->listener->listen($port, '127.0.0.1', $options);
                $this->listener->listen($port, '[::1]', $options);
                break;

            default:
                $this->listener->listen($port, $address, $options);
        }
    }
}