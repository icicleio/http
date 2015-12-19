<?php
namespace Icicle\Http\Driver;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;
use Icicle\Socket\Socket;

interface Driver
{
    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Request
     */
    public function readRequest(Socket $socket, float $timeout = 0): \Generator;

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function buildResponse(
        Socket $socket,
        Response $response,
        Request $request = null,
        float $timeout = 0,
        bool $allowPersistent = false
    ): \Generator;

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve int Number of bytes written to the socket.
     */
    public function writeResponse(
        Socket $socket,
        Response $response,
        Request $request = null,
        float $timeout = 0
    ): \Generator;

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param float|int $timeout
     *
     * @return \Generator
     */
    public function readResponse(Socket $socket, float $timeout = 0): \Generator;

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     */
    public function buildRequest(
        Socket $socket,
        Request $request,
        float $timeout = 0,
        bool $allowPersistent = false
    ): \Generator;

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     *
     * @return \Generator
     */
    public function writeRequest(Socket $socket, Request $request, float $timeout = 0): \Generator;
}
