<?php
namespace Icicle\Http\Driver\Builder;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;

interface Builder
{
    /**
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Http\Message\Request|null $request
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Icicle\Http\Message\Response
     */
    public function buildOutgoingResponse(
        Response $response,
        Request $request = null,
        float $timeout = 0,
        bool $allowPersistent = false
    ): \Generator;

    /**
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Request
     */
    public function buildOutgoingRequest(
        Request $request,
        float $timeout = 0,
        bool $allowPersistent = false
    ): \Generator;

    /**
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     *
     * @return \Icicle\Http\Message\Request
     */
    public function buildIncomingRequest(Request $request, float $timeout = 0): \Generator;

    /**
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     *
     * @return \Icicle\Http\Message\Response
     */
    public function buildIncomingResponse(
        Response $response,
        Request $request,
        float $timeout = 0
    ): \Generator;
}
