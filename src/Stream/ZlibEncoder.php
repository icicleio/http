<?php
namespace Icicle\Http\Stream;

use Icicle\Http\Exception\Error;
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
     * @param int $type Compression type. Use GZIP or DEFLATE constants.
     * @param int $level Compression level.
     *
     * @throws \Icicle\Http\Exception\Error If the zlib extension is not loaded.
     */
    public function __construct($type, $level = self::DEFAULT_LEVEL)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('zlib')) {
            throw new Error('zlib extension required to decode compressed streams.');
        } // @codeCoverageIgnoreEnd

        switch ($type) {
            case self::GZIP:
            case self::DEFLATE:
                $this->type = $type;
                break;

            default:
                throw new Error('Invalid compression type.');
        }

        parent::__construct();

        $this->level = (int) $level;
    }

    /**
     * @param string $data
     * @param float|int $timeout
     * @param bool $end
     *
     * @return \Generator
     */
    public function send($data, $timeout = 0, $end = false)
    {
        $this->buffer .= $data;

        if (!$end) {
            yield 0;
            return;
        }

        yield parent::send(zlib_encode($this->buffer, $this->type, $this->level), $timeout, true);
    }
}
