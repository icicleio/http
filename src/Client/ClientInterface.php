<?php
namespace Icicle\Http\Client;

use Icicle\Http\Message\RequestInterface;
use Icicle\Stream\ReadableStreamInterface;

interface ClientInterface
{
    /**
     * @param string $method
     * @param string|\Icicle\Http\Message\UriInterface $uri
     * @param string[]|null $headers
     * @param \Icicle\Stream\ReadableStreamInterface|null $body
     * @param float|int|null $timeout
     * @param mixed[] $options
     *
     * @return mixed
     */
    public function request(
        $method,
        $uri,
        array $headers = null,
        ReadableStreamInterface $body = null,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        array $options = null
    );

    /**
     * @param   \Icicle\Http\Message\RequestInterface $request
     * @param   float|int|null $timeout
     * @param   mixed[] $options
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    public function send(
        RequestInterface $request,
        $timeout = ExecutorInterface::DEFAULT_TIMEOUT,
        array $options = null
    );
}