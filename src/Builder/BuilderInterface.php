<?php
namespace Icicle\Http\Builder;

use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\ResponseInterface;
use Icicle\Socket\SocketInterface;

interface BuilderInterface
{
    /**
     * @param \Icicle\Socket\SocketInterface
     * @param \Icicle\Http\Message\ResponseInterface $response
     * @param \Icicle\Http\Message\RequestInterface|null $request
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Icicle\Http\Message\ResponseInterface
     */
    public function buildOutgoingResponse(
        SocketInterface $socket,
        ResponseInterface $response,
        RequestInterface $request = null,
        $timeout = 0,
        $allowPersistent = false
    );

    /**
     * @param \Icicle\Socket\SocketInterface
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\RequestInterface
     */
    public function buildOutgoingRequest(
        SocketInterface $socket,
        RequestInterface $request,
        $timeout = 0,
        $allowPersistent = false
    );

    /**
     * @param \Icicle\Socket\SocketInterface
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param float|int $timeout
     *
     * @return \Icicle\Http\Message\RequestInterface
     */
    public function buildIncomingRequest(SocketInterface $socket, RequestInterface $request, $timeout = 0);

    /**
     * @param \Icicle\Socket\SocketInterface
     * @param \Icicle\Http\Message\ResponseInterface $response
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param float|int $timeout
     *
     * @return \Icicle\Http\Message\ResponseInterface
     */
    public function buildIncomingResponse(
        SocketInterface $socket,
        ResponseInterface $response,
        RequestInterface $request,
        $timeout = 0
    );
}
