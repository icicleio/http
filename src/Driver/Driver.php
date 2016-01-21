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
    public function readRequest(Socket $socket, $timeout = 0);

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function buildResponse(
        Response $response,
        Request $request = null,
        $timeout = 0,
        $allowPersistent = false
    );

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
    public function writeResponse(Socket $socket, Response $response, Request $request = null, $timeout = 0);

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param float|int $timeout
     *
     * @return \Generator
     */
    public function readResponse(Socket $socket, $timeout = 0);

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     */
    public function buildRequest(Request $request, $timeout = 0, $allowPersistent = false);

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param \Icicle\Http\Message\Request $request
     * @param float|int $timeout
     *
     * @return \Generator
     */
    public function writeRequest(Socket $socket, Request $request, $timeout = 0);
}
