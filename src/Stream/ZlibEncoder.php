<?php
namespace Icicle\Http\Stream;

use Icicle\Exception\{InvalidArgumentError, UnsupportedError};
use Icicle\Stream\{Exception\FailureException, MemoryStream};

class ZlibEncoder extends MemoryStream
{
    const GZIP = \ZLIB_ENCODING_GZIP;
    const DEFLATE = \ZLIB_ENCODING_RAW;

    const DEFAULT_LEVEL = -1;

    /**
     * @var resource
     */
    private $resource;

    /**
     * @param int $type Compression type. Use GZIP or DEFLATE constants defined in this class.
     * @param int $level Compression level.
     * @param int $hwm
     *
     * @throws \Icicle\Exception\UnsupportedError If the zlib extension is not loaded.
     * @throws \Icicle\Exception\InvalidArgumentError If the $type is not a valid compression type.
     */
    public function __construct(int $type, int $level = self::DEFAULT_LEVEL, int $hwm = 0)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('zlib')) {
            throw new UnsupportedError('zlib extension required to decode compressed streams.');
        } // @codeCoverageIgnoreEnd

        if (-1 > $level || 9 < $level) {
            throw new InvalidArgumentError('Level must be between -1 (default) and 9.');
        }

        switch ($type) {
            case self::GZIP:
            case self::DEFLATE:
                $this->resource = deflate_init($type, ['level' => $level]);
                break;

            default:
                throw new InvalidArgumentError('Invalid compression type.');
        }

        if (null === $this->resource) {
            throw new FailureException('Could not initialize deflate handle.');
        }

        parent::__construct($hwm);
    }

    /**
     * {@inheritdoc}
     */
    protected function send(string $data, float $timeout = 0, bool $end = false): \Generator
    {
        if (false === ($data = deflate_add($this->resource, $data, $end ? \ZLIB_FINISH : \ZLIB_SYNC_FLUSH))) {
            throw new FailureException('Failed adding date to deflate stream.');
        }

        if ('' === $data) {
            return 0;
        }

        return yield from parent::send($data, $timeout, $end);
    }
}
