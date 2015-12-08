<?php
namespace Icicle\Http\Server;

use Icicle\Http\Message\Request;
use Icicle\Socket\Socket;

interface Driver
{
    /**
     * @param \Icicle\Http\Server\RequestHandler $handler
     * @param \Icicle\Socket\Socket $socket
     * @param int $cryptoMethod
     * @param float $timeout
     * @param bool $allowPersistent
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function process(Socket $socket, $cryptoMethod, $timeout, $allowPersistent);

    public function readRequest(Socket $socket, $timeout);

    public function createResponse(Request $request, Socket $socket, $timeout, $allowPersistent);
}
