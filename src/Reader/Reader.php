<?php
namespace Icicle\Http\Reader;

use Icicle\Stream\ReadableStream;

interface Reader
{
    /**
     * @coroutine
     *
     * @param \Icicle\Stream\ReadableStream $stream
     * @param float|int|null $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Response
     *
     * @throws \Icicle\Http\Exception\MessageException
     * @throws \Icicle\Stream\Exception\UnreadableException
     */
    public function readResponse(ReadableStream $stream, $timeout = 0);

    /**
     * @coroutine
     *
     * @param \Icicle\Stream\ReadableStream $stream
     * @param float|int|null $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\Request
     *
     * @throws \Icicle\Http\Exception\MessageException
     * @throws \Icicle\Stream\Exception\UnreadableException
     */
    public function readRequest(ReadableStream $stream, $timeout = 0);
}
