<?php
namespace Icicle\Http\Server;

use Icicle\Http\Message\Request;
use Icicle\Socket\Socket;

interface RequestHandler
{
    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response $response
     */
    public function onRequest(Request $request, Socket $socket);

    /**
     * @coroutine
     *
     * @param int $code
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     */
    public function onError($code, Socket $socket);
}
