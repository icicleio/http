<?php
namespace Icicle\Http\Stream;

use Icicle\Http\Exception\Error;
use Icicle\Http\Exception\MessageException;
use Icicle\Promise;
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
     * @param int|null $maxLength Maximum length of compressed data; null for no max length.
     *
     * @throws \Icicle\Http\Exception\LogicException If the zlib extension is not loaded.
     */
    public function __construct($maxLength = null)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('zlib')) {
            throw new Error('zlib extension required to decode compressed streams.');
        } // @codeCoverageIgnoreEnd

        parent::__construct();
        $this->buffer = new Buffer();
        $this->maxLength = $this->parseLength($maxLength);
    }

    /**
     * @param string $data
     * @param float|int $timeout
     * @param bool $end
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function send($data, $timeout = 0, $end = false)
    {
        $this->buffer->push($data);

        if (null !== $this->maxLength && $this->buffer->getLength() > $this->maxLength) {
            return parent::send('', $timeout, true)->then(function () {
                throw new MessageException(413, 'Message body too long.');
            });
        }

        if (!$end) {
            return Promise\resolve(0);
        }

        // Error reporting suppressed since zlib_decode() emits a warning if decompressing fails. Checked below.
        $data = @zlib_decode($this->buffer->drain());

        if (false === $data) {
            return parent::send('', $timeout, true)->then(function () {
                throw new MessageException(400, 'Could not decode compressed stream.');
            });
        }

        return parent::send($data, $timeout, true);
    }
}
