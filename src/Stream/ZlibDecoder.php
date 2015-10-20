<?php
namespace Icicle\Http\Stream;

use Icicle\Http\Exception\InvalidArgumentError;
use Icicle\Http\Exception\MessageException;
use Icicle\Http\Exception\UnsupportedError;
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
     * @param int|null $maxLength Maximum length of compressed data; null for no max length.
     *
     * @throws \Icicle\Http\Exception\InvalidArgumentError If the max length is negative.
     * @throws \Icicle\Http\Exception\UnsupportedError If the zlib extension is not loaded.
     */
    public function __construct($maxLength = 0)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('zlib')) {
            throw new UnsupportedError('zlib extension required to decode compressed streams.');
        } // @codeCoverageIgnoreEnd

        parent::__construct();

        $this->maxLength = (int) $maxLength;
        if (0 > $this->maxLength) {
            throw new InvalidArgumentError('The max length must be a non-negative integer.');
        }

    }

    /**
     * @param string $data
     * @param float|int $timeout
     * @param bool $end
     *
     * @return \Generator
     *
     * @throws \Icicle\Http\Exception\MessageException If compressed message body exceeds the max length or if decoding
     *    the compressed stream fails.
     */
    public function send($data, $timeout = 0, $end = false)
    {
        $this->buffer .= $data;

        if (0 !== $this->maxLength && strlen($this->buffer) > $this->maxLength) {
            yield parent::send('', $timeout, true);
            throw new MessageException(413, 'Message body too long.');
        }

        if (!$end) {
            yield 0;
            return;
        }

        // Error reporting suppressed since zlib_decode() emits a warning if decompressing fails. Checked below.
        $data = @zlib_decode($this->buffer);
        $this->buffer = '';

        if (false === $data) {
            yield parent::send('', $timeout, true);
            throw new MessageException(400, 'Could not decode compressed stream.');
        }

        yield parent::send($data, $timeout, true);
    }
}
