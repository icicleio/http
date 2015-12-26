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
     * @param int $hwm
     *
     * @throws \Icicle\Exception\UnsupportedError If the zlib extension is not loaded.
     * @throws \Icicle\Exception\InvalidArgumentError If the $type is not a valid compression type.
     */
    public function __construct($type, $level = self::DEFAULT_LEVEL, $hwm = 0)
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

        $this->level = (int) $level;
    }

    /**
     * {@inheritdoc}
     */
    protected function send($data, $timeout = 0, $end = false)
    {
        $this->buffer .= $data;

        if (!$end) {
            yield 0;
            return;
        }

        yield parent::send(zlib_encode($this->buffer, $this->type, $this->level), $timeout, true);
    }
}
