<?php
namespace Icicle\Http\Parser;

use Icicle\Socket\Client\ClientInterface;
use Icicle\Stream\ReadableStreamInterface;

interface ParserInterface
{
    /**
     * @coroutine
     *
     * @param   \Icicle\Socket\Client\ClientInterface $client
     * @param   int $maxSize
     * @param   float|int|null $timeout
     *
     * @return  \Generator
     *
     * @resolve string
     *
     * @reject  \Icicle\Http\Exception\MessageHeaderSizeException
     * @reject  \Icicle\Socket\Exception\UnreadableException
     */
    public function readMessage(ClientInterface $client, $maxSize, $timeout = null);

    /**
     * @param   string $message
     * @param   \Icicle\Stream\ReadableStreamInterface|null $stream
     *
     * @return  \Icicle\Http\Message\Request
     *
     * @throws  \Icicle\Http\Exception\ParseException If parsing the message fails.
     * @throws  \Icicle\Http\Exception\MessageException If there is invalid data in the response.
     * @throws  \Icicle\Http\Exception\LogicException If a body is in the response when a stream is provided.
     */
    public function parseResponse($message, ReadableStreamInterface $stream = null);

    /**
     * @param   string $message
     * @param   \Icicle\Stream\ReadableStreamInterface|null $stream
     *
     * @return  \Icicle\Http\Message\Response
     *
     * @throws  \Icicle\Http\Exception\ParseException If parsing the message fails.
     * @throws  \Icicle\Http\Exception\MessageException If there is invalid data in the request.
     * @throws  \Icicle\Http\Exception\LogicException If a body is in the request when a stream is provided.
     */
    public function parseRequest($message, ReadableStreamInterface $stream = null);
}
