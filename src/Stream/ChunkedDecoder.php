<?php
namespace Icicle\Http\Stream;

use Icicle\Http\{Exception\MessageException, Message\Response};
use Icicle\Stream\{MemoryStream, Structures\Buffer};

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
    public function __construct(int $hwm = 0)
    {
        parent::__construct($hwm);

        $this->buffer = new Buffer();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Icicle\Http\Exception\MessageException If an invalid chunk length is found.
     */
    protected function send(string $data, float $timeout = 0, bool $end = false): \Generator
    {
        $this->buffer->push($data);

        $data = '';

        while (!$this->buffer->isEmpty()) {
            if (0 === $this->length) { // Read chunk length.
                if (false === ($position = $this->buffer->search("\r\n"))) {
                    return yield from parent::send($data, $timeout, $end);
                }

                $length = rtrim($this->buffer->remove($position + 2), "\r\n");

                if ($position = strpos($length, ';')) {
                    $length = substr($length, 0, $position);
                }

                if (!preg_match('/^[a-f0-9]+$/i', $length)) {
                    yield from parent::send('', $timeout, true);
                    throw new MessageException(Response::BAD_REQUEST, 'Invalid chunk length.');
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

        return yield from parent::send($data, $timeout, $end);
    }
}
