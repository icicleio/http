<?php
namespace Icicle\Http\Client;

use Icicle\Http\Message\RequestInterface;
use Icicle\Socket\Client\ClientInterface as SocketClientInterface;

interface RequesterInterface
{
    const DEFAULT_TIMEOUT = 15;

    /**
     * @param   \Icicle\Socket\Client\ClientInterface $client
     * @param   \Icicle\Http\Message\RequestInterface $request
     * @param   float|int|null $timeout
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    public function request(SocketClientInterface $client, RequestInterface $request, $timeout = self::DEFAULT_TIMEOUT);
}
