<?php
namespace Icicle\Http\Builder;

use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\ResponseInterface;

interface BuilderInterface
{
    /**
     * @param   \Icicle\Http\Message\ResponseInterface $response
     * @param   \Icicle\Http\Message\RequestInterface|null $request
     * @param   float|null $timeout
     * @param   bool $allowPersistent
     *
     * @return  \Icicle\Http\Message\ResponseInterface
     */
    public function buildOutgoingResponse(
        ResponseInterface $response,
        RequestInterface $request = null,
        $timeout = null,
        $allowPersistent = false
    );

    /**
     * @param   \Icicle\Http\Message\RequestInterface $request
     * @param   float|null $timeout
     *
     * @return  \Generator
     *
     * @resolve \Icicle\Http\Message\RequestInterface
     */
    public function buildOutgoingRequest(RequestInterface $request, $timeout = null);

    /**
     * @param   \Icicle\Http\Message\RequestInterface $request
     * @param   float|null $timeout
     *
     * @return  \Icicle\Http\Message\RequestInterface
     */
    public function buildIncomingRequest(RequestInterface $request, $timeout = null);

    /**
     * @param   \Icicle\Http\Message\ResponseInterface $response
     * @param   float|null $timeout
     *
     * @return  \Icicle\Http\Message\ResponseInterface
     */
    public function buildIncomingResponse(ResponseInterface $response, $timeout = null);
}
