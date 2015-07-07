<?php
namespace Icicle\Http\Builder;

use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\ResponseInterface;

interface BuilderInterface
{
    /**
     * @param \Icicle\Http\Message\ResponseInterface $response
     * @param \Icicle\Http\Message\RequestInterface|null $request
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Icicle\Http\Message\ResponseInterface
     */
    public function buildOutgoingResponse(
        ResponseInterface $response,
        RequestInterface $request = null,
        $timeout = 0,
        $allowPersistent = false
    );

    /**
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\RequestInterface
     */
    public function buildOutgoingRequest(RequestInterface $request, $timeout = 0);

    /**
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param float|int $timeout
     *
     * @return \Icicle\Http\Message\RequestInterface
     */
    public function buildIncomingRequest(RequestInterface $request, $timeout = 0);

    /**
     * @param \Icicle\Http\Message\ResponseInterface $response
     * @param float|int $timeout
     *
     * @return \Icicle\Http\Message\ResponseInterface
     */
    public function buildIncomingResponse(ResponseInterface $response, $timeout = 0);
}
