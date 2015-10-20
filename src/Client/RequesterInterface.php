<?php
namespace Icicle\Http\Client;

use Icicle\Http\Message\RequestInterface;
use Icicle\Socket\SocketInterface;

interface RequesterInterface
{
    const DEFAULT_TIMEOUT = 15;

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\SocketInterface $socket
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param mixed[] $options
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface
     *
     * @reject \Icicle\Http\Exception\MessageException
     * @reject \Icicle\Http\Exception\ParseException
     */
    public function request(SocketInterface $socket, RequestInterface $request, array $options = []);
}
