<?php
namespace Icicle\Http\Stream;

use Icicle\Exception\{InvalidArgumentError, UnsupportedError};
use Icicle\Stream\{Exception\FailureException, MemoryStream};

class ZlibDecoder extends MemoryStream
{
    const GZIP = \ZLIB_ENCODING_GZIP;
    const DEFLATE = \ZLIB_ENCODING_RAW;

    /**
     * @var resource
     */
    private $resource;

    /**
     * @param int $type Compression type. Use GZIP or DEFLATE constants defined in this class.
     * @param int $hwm
     *
     * @throws \Icicle\Exception\UnsupportedError If the zlib extension is not loaded.
     */
    public function __construct(int $type, int $hwm = 0)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('zlib')) {
            throw new UnsupportedError('zlib extension required to decode compressed streams.');
        } // @codeCoverageIgnoreEnd

        parent::__construct($hwm);

        switch ($type) {
            case self::GZIP:
            case self::DEFLATE:
                $this->resource = inflate_init($type);
                break;

            default:
                throw new InvalidArgumentError('Invalid compression type.');
        }

        if (null === $this->resource) {
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
        if (false === ($data = inflate_add($this->resource, $data, $end ? \ZLIB_FINISH : \ZLIB_SYNC_FLUSH))) {
            throw new FailureException('Failed adding date to inflate stream.');
        }

        return yield from parent::send($data, $timeout, $end);
    }
}
