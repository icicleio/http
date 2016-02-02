<?php
namespace Icicle\Http\Server;

use Icicle\Http\Message\Request;
use Icicle\Socket\Socket;

interface RequestHandler
{
    /**
     * @param \Icicle\Http\Message\Request $request
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator|\Icicle\Awaitable\Awaitable|\Icicle\Http\Message\Response
     */
    public function onRequest(Request $request, Socket $socket);

    /**
     * @param int $code
     * @param \Icicle\Socket\Socket $socket
     *
     * @return \Generator|\Icicle\Awaitable\Awaitable|\Icicle\Http\Message\Response
     */
    public function onError(int $code, Socket $socket);
}
