<?php
namespace Icicle\Http\Stream;

use Icicle\Http\Exception\LogicException;
use Icicle\Http\Exception\MessageBodySizeException;
use Icicle\Promise;
use Icicle\Stream\Exception\RuntimeException;
use Icicle\Stream\Stream;
use Icicle\Stream\Structures\Buffer;

class ZlibDecoder extends Stream
{
    /**
     * @var \Icicle\Stream\Structures\Buffer
     */
    private $buffer;

    /**
     * @var int|null
     */
    private $maxLength;

    /**
     * @param   int|null $maxLength Maximum length of compressed data; null for no max length.
     *
     * @throws  \Icicle\Http\Exception\LogicException If the zlib extension is not loaded.
     */
    public function __construct($maxLength = null)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('zlib')) {
            throw new LogicException('zlib extension required to decode compressed streams.');
        } // @codeCoverageIgnoreEnd

        parent::__construct();
        $this->buffer = new Buffer();
        $this->maxLength = $this->parseLength($maxLength);
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

        if (null !== $this->maxLength && $this->buffer->getLength() > $this->maxLength) {
            return parent::send('', true)->then(function () {
                throw new MessageBodySizeException('Message body too long.');
            });
        }

        if (!$end) {
            return Promise\resolve(0);
        }

        // Error reporting suppressed since zlib_decode() emits a warning if decompressing fails. Checked below.
        $data = @zlib_decode($this->buffer->drain());

        if (false === $data) {
            return parent::send('', true)->then(function () {
                throw new RuntimeException('Could not decode compressed stream.');
            });
        }

        return parent::send($data, true);
    }
}
