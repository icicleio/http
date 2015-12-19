<?php
namespace Icicle\Http\Stream;

use Icicle\Exception\InvalidArgumentError;
use Icicle\Exception\UnsupportedError;
use Icicle\Stream\MemoryStream;

class ZlibEncoder extends MemoryStream
{
    const GZIP = ZLIB_ENCODING_GZIP;
    const DEFLATE = ZLIB_ENCODING_RAW;

    const DEFAULT_LEVEL = -1;

    /**
     * @var string
     */
    private $buffer = '';

    /**
     * @var int
     */
    private $type;

    /**
     * @var int
     */
    private $level;

    /**
     * @param int $type Compression type. Use GZIP or DEFLATE constants defined in this class.
     * @param int $level Compression level.
     *
     * @throws \Icicle\Exception\UnsupportedError If the zlib extension is not loaded.
     * @throws \Icicle\Exception\InvalidArgumentError If the $type is not a valid compression type.
     */
    public function __construct(int $type, int $level = self::DEFAULT_LEVEL)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('zlib')) {
            throw new UnsupportedError('zlib extension required to decode compressed streams.');
        } // @codeCoverageIgnoreEnd

        switch ($type) {
            case self::GZIP:
            case self::DEFLATE:
                $this->type = $type;
                break;

            default:
                throw new InvalidArgumentError('Invalid compression type.');
        }

        parent::__construct();

        $this->level = $level;
    }

    /**
     * {@inheritdoc}
     */
    protected function send(string $data, float $timeout = 0, bool $end = false): \Generator
    {
        $this->buffer .= $data;

        if (!$end) {
            return 0;
        }

        return yield from parent::send(zlib_encode($this->buffer, $this->type, $this->level), $timeout, true);
    }
}
