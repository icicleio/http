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
        $timeout = 0,
        $allowPersistent = false
    );

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
        $timeout = 0,
        $allowPersistent = false
    );

    /**
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     *
     * @return \Icicle\Http\Message\Request
     */
    public function buildIncomingRequest(Request $request, $timeout = 0);

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
        $timeout = 0
    );
}
