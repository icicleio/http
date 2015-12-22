<?php
namespace Icicle\Http\Driver\Reader;

use Icicle\Socket\Socket;

interface Reader
{
    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param float|int|null $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     *
     * @throws \Icicle\Http\Exception\MessageException
     * @throws \Icicle\Stream\Exception\UnreadableException
     */
    public function readResponse(Socket $socket, float $timeout = 0): \Generator;

    /**
     * @coroutine
     *
     * @param \Icicle\Socket\Socket $socket
     * @param float|int|null $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Request
     *
     * @throws \Icicle\Http\Exception\MessageException
     * @throws \Icicle\Stream\Exception\UnreadableException
     */
    public function readRequest(Socket $socket, float $timeout = 0): \Generator;
}
