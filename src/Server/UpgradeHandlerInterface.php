<?php
namespace Icicle\Http\Server;

use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\ResponseInterface;
use Icicle\Socket\SocketInterface;

interface UpgradeHandlerInterface extends RequestHandlerInterface
{
    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param \Icicle\Http\Message\ResponseInterface $response
     * @param \Icicle\Socket\SocketInterface $socket
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function onUpgrade(RequestInterface $request, ResponseInterface $response, SocketInterface $socket);
}