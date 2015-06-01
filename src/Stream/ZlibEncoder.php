<?php
namespace Icicle\Http\Stream;

use Icicle\Http\Exception\InvalidArgumentException;
use Icicle\Http\Exception\LogicException;
use Icicle\Promise;
use Icicle\Stream\Stream;
use Icicle\Stream\Structures\Buffer;

class ZlibEncoder extends Stream
{
    const GZIP = ZLIB_ENCODING_GZIP;
    const DEFLATE = ZLIB_ENCODING_RAW;

    const DEFAULT_LEVEL = -1;

    /**
     * @var \Icicle\Stream\Structures\Buffer
     */
    private $buffer;

    /**
     * @var int
     */
    private $type;

    /**
     * @var int
     */
    private $level;

    /**
     * @param   int $type Compression type. Use GZIP or DEFLATE constants.
     * @param   int $level Compression level.
     *
     * @throws  \Icicle\Http\Exception\LogicException If the zlib extension is not loaded.
     */
    public function __construct($type, $level = self::DEFAULT_LEVEL)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('zlib')) {
            throw new LogicException('zlib extension required to decode compressed streams.');
        } // @codeCoverageIgnoreEnd

        switch ($type) {
            case self::GZIP:
            case self::DEFLATE:
                $this->type = $type;
                break;

            default:
                throw new InvalidArgumentException('Invalid compression type.');
        }

        parent::__construct();

        $this->buffer = new Buffer();
        $this->level = (int) $level;
    }

    /**
     * @param   string $data
     * @param   bool $end
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    public function send($data, $end = false)
    {
        $this->buffer->push($data);

        if (!$end) {
            return Promise\resolve(0);
        }

        $data = zlib_encode($this->buffer, $this->type, $this->level);

        return parent::send($data, true);
    }
}
