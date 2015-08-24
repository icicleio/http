<?php
namespace Icicle\Http\Reader;

use Icicle\Stream\ReadableStreamInterface;

interface ReaderInterface
{
    /**
     * @coroutine
     *
     * @param \Icicle\Stream\ReadableStreamInterface $stream
     * @param float|int|null $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface
     *
     * @throws \Icicle\Http\Exception\MessageException
     * @throws \Icicle\Stream\Exception\UnreadableException
     */
    public function readResponse(ReadableStreamInterface $stream, $timeout = 0);

    /**
     * @coroutine
     *
     * @param \Icicle\Stream\ReadableStreamInterface $stream
     * @param float|int|null $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\RequestInterface
     *
     * @throws \Icicle\Http\Exception\MessageException
     * @throws \Icicle\Stream\Exception\UnreadableException
     */
    public function readRequest(ReadableStreamInterface $stream, $timeout = 0);
}
