<?php
namespace Icicle\Http\Stream;

use Icicle\Stream\Stream;

class LimitStream extends Stream
{
    private $remaining;

    /**
     * @param int $length
     * @param int $hwm
     */
    public function __construct($length, $hwm = null)
    {
        parent::__construct($hwm);

        $this->remaining = $this->parseLength($length);
        if (0 == $this->remaining) { // match null or 0.
            $this->end();
        }
    }

    /**
     * @param string $data
     * @param float|int $timeout
     * @param bool $end
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    protected function send($data, $timeout = 0, $end = false)
    {
        $this->remaining -= strlen($data);

        if (0 > $this->remaining) {
            $data = substr($data, 0, $this->remaining);
        }

        if (0 >= $this->remaining) {
            return parent::send($data, $timeout, true);
        }

        return parent::send($data, $timeout, $end);
    }
}
