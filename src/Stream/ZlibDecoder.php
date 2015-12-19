<?php
namespace Icicle\Http\Stream;

use Icicle\Exception\InvalidArgumentError;
use Icicle\Exception\UnsupportedError;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Message\Response;
use Icicle\Stream\MemoryStream;

class ZlibDecoder extends MemoryStream
{
    /**
     * @var string
     */
    private $buffer = '';

    /**
     * @var int
     */
    private $maxLength;

    /**
     * @param int|null $maxLength Maximum length of compressed data; 0 for no max length.
     *
     * @throws \Icicle\Exception\InvalidArgumentError If the max length is negative.
     * @throws \Icicle\Exception\UnsupportedError If the zlib extension is not loaded.
     */
    public function __construct(int $maxLength = 0)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('zlib')) {
            throw new UnsupportedError('zlib extension required to decode compressed streams.');
        } // @codeCoverageIgnoreEnd

        parent::__construct();

        $this->maxLength = $maxLength;
        if (0 > $this->maxLength) {
            throw new InvalidArgumentError('The max length must be a non-negative integer.');
        }

    }

    /**
     * {@inheritdoc}
     *
     * @throws \Icicle\Http\Exception\MessageException If compressed message body exceeds the max length or if decoding
     *    the compressed stream fails.
     */
    protected function send(string $data, float $timeout = 0, bool $end = false): \Generator
    {
        $this->buffer .= $data;

        if (0 !== $this->maxLength && strlen($this->buffer) > $this->maxLength) {
            yield from parent::send('', $timeout, true);
            throw new MessageException(Response::REQUEST_ENTITY_TOO_LARGE, 'Message body too long.');
        }

        if (!$end) {
            return 0;
        }

        // Error reporting suppressed since zlib_decode() emits a warning if decompressing fails. Checked below.
        $data = @zlib_decode($this->buffer);
        $this->buffer = '';

        if (false === $data) {
            yield from parent::send('', $timeout, true);
            throw new MessageException(Response::BAD_REQUEST, 'Could not decode compressed stream.');
        }

        return yield from parent::send($data, $timeout, true);
    }
}
