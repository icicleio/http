<?php
namespace Icicle\Http\Stream;

use Icicle\Exception\UnsupportedError;
use Icicle\Stream\Exception\FailureException;
use Icicle\Stream\MemoryStream;

class ZlibDecoder extends MemoryStream
{
    /**
     * @var resource
     */
    private $resource;

    /**
     * @throws \Icicle\Exception\UnsupportedError If the zlib extension is not loaded.
     */
    public function __construct(int $hwm = 0)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('zlib')) {
            throw new UnsupportedError('zlib extension required to decode compressed streams.');
        } // @codeCoverageIgnoreEnd

        parent::__construct($hwm);

        $this->resource = inflate_init();

        if (false === $this->resource) {
            throw new FailureException('Could not initialize inflate handle.');
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
        if (false === ($data = inflate_add($this->resource, $data, ZLIB_SYNC_FLUSH))) {
            throw new FailureException('Failed adding date to inflate stream.');
        }

        if ('' === $data) {
            return 0;
        }

        return yield from parent::send($data, $timeout, $end);
    }
}
