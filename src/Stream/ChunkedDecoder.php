<?php
namespace Icicle\Http\Stream;

use Icicle\Http\Exception\MessageException;
use Icicle\Stream\MemoryStream;
use Icicle\Stream\Structures\Buffer;

class ChunkedDecoder extends MemoryStream
{
    /**
     * @var int
     */
    private $length = 0;

    /**
     * @var \Icicle\Stream\Structures\Buffer
     */
    private $buffer;

    /**
     * @param int $hwm
     */
    public function __construct($hwm = 0)
    {
        parent::__construct($hwm);

        $this->buffer = new Buffer();
    }

    /**
     * @param string $data
     * @param float|int $timeout
     * @param bool $end
     *
     * @return \Generator
     *
     * @resolve int
     *
     * @throws \Icicle\Http\Exception\MessageException
     */
    public function send($data, $timeout = 0, $end = false)
    {
        $this->buffer->push($data);

        $data = '';

        while (!$this->buffer->isEmpty()) {
            if (0 === $this->length) { // Read chunk length.
                if (false === ($position = $this->buffer->search("\n"))) {
                    yield parent::send($data, $timeout, $end);
                    return;
                }

                $length = rtrim($this->buffer->remove($position + 1), "\r\n");

                if ($position = strpos($length, ';')) {
                    $length = substr($length, 0, $position);
                }

                if (!preg_match('/^[a-f0-9]+$/i', $length)) {
                    yield parent::send('', $timeout, true);
                    throw new MessageException(400, 'Invalid chunk length.');
                }

                $this->length = hexdec($length) + 2;

                if (2 === $this->length) { // Termination chunk.
                    $end = true;
                }
            }

            if (2 < $this->length) { // Read chunk.
                $buffer = $this->buffer->remove($this->length - 2);
                $this->length -= strlen($buffer);
                $data .= $buffer;
            }

            if (2 >= $this->length) { // Remove \r\n after chunk.
                $this->length -= strlen($this->buffer->remove($this->length));
            }
        }

        yield parent::send($data, $timeout, $end);
    }
}
