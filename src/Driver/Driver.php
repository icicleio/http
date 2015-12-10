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
     * @param int $cryptoMethod
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve null
     */
    //public function process(Socket $socket, $cryptoMethod, $timeout = 0, $allowPersistent = true);

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
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
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
        $timeout = 0,
        $allowPersistent = false
    );

    /**
     * @param \Icicle\Http\Message\Response $response
     * @param \Icicle\Socket\Socket $socket
     * @param bool $body
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve int Number of bytes written to the socket.
     */
    public function writeResponse(Socket $socket, Response $response, Request $request = null, $timeout = 0);
}
