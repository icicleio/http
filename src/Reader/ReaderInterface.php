<?php
namespace Icicle\Http\Reader;

use Icicle\Stream\ReadableStreamInterface;

interface ReaderInterface
{
    /**
     * @coroutine
     *
     * @param   \Icicle\Stream\ReadableStreamInterface $stream
     * @param   int $maxSize
     * @param   float|int|null $timeout
     *
     * @return  \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface
     *
     * @reject  \Icicle\Http\Exception\MessageHeaderSizeException
     * @reject  \Icicle\Socket\Exception\UnreadableException
     */
    public function readResponse(ReadableStreamInterface $stream, $maxSize, $timeout = null);

    /**
     * @coroutine
     *
     * @param   \Icicle\Stream\ReadableStreamInterface $stream
     * @param   int $maxSize
     * @param   float|int|null $timeout
     *
     * @return  \Generator
     *
     * @resolve \Icicle\Http\Message\RequestInterface
     *
     * @reject  \Icicle\Http\Exception\MessageHeaderSizeException
     * @reject  \Icicle\Socket\Exception\UnreadableException
     */
    public function readRequest(ReadableStreamInterface $stream, $maxSize, $timeout = null);
}
