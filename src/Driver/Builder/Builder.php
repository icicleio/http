<?php
namespace Icicle\Http\Driver\Builder;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Socket\Socket;

interface Builder
{
    /**
     * @param \Icicle\Socket\Socket
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Http\Message\Request|null $request
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Icicle\Http\Message\Response
     */
    public function buildOutgoingResponse(
        Socket $socket,
        Response $response,
        Request $request = null,
        float $timeout = 0,
        bool $allowPersistent = false
    ): \Generator;

    /**
     * @param \Icicle\Socket\Socket
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Request
     */
    public function buildOutgoingRequest(
        Socket $socket,
        Request $request,
        float $timeout = 0,
        bool $allowPersistent = false
    ): \Generator;

    /**
     * @param \Icicle\Socket\Socket
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     *
     * @return \Icicle\Http\Message\Request
     */
    public function buildIncomingRequest(Socket $socket, Request $request, $timeout = 0): \Generator;

    /**
     * @param \Icicle\Socket\Socket
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     *
     * @return \Icicle\Http\Message\Response
     */
    public function buildIncomingResponse(
        Socket $socket,
        Response $response,
        Request $request,
        float $timeout = 0
    ): \Generator;
}
