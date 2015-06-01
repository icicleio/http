<?php
namespace Icicle\Http\Client;

use Icicle\Http\Message\RequestInterface;
use Icicle\Socket\Client\ClientInterface as SocketClientInterface;

interface RequesterInterface
{
    const DEFAULT_TIMEOUT = 15;

    /**
     * @coroutine
     *
     * @param   \Icicle\Socket\Client\ClientInterface $client
     * @param   \Icicle\Http\Message\RequestInterface $request
     * @param   float|int|null $timeout
     *
     * @return  \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface
     *
     * @reject  \Icicle\Http\Exception\MessageException
     * @reject  \Icicle\Http\Exception\ParseException
     */
    public function request(SocketClientInterface $client, RequestInterface $request, $timeout = self::DEFAULT_TIMEOUT);
}
