<?php
namespace Icicle\Http\Server;

use Icicle\Http\Message\RequestInterface;
use Icicle\Socket\SocketInterface;

interface RequestHandlerInterface
{
    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param \Icicle\Socket\SocketInterface $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface $response
     */
    public function onRequest(RequestInterface $request, SocketInterface $socket);

    /**
     * @coroutine
     *
     * @param int $code
     * @param \Icicle\Socket\SocketInterface $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface
     */
    public function onError($code, SocketInterface $socket);
}
