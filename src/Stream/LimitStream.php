<?php
namespace Icicle\Http\Stream;

use Icicle\Stream\Stream;

class LimitStream extends Stream
{
    private $remaining;

    public function __construct($length, $hwm = null)
    {
        parent::__construct($hwm);

        $this->remaining = $this->parseLength($length);
        if (0 == $this->remaining) { // match null or 0.
            $this->end();
        }
    }

    protected function send($data, $end = false)
    {
        $this->remaining -= strlen($data);

        if (0 > $this->remaining) {
            $data = substr($data, 0, $this->remaining);
        }

        if (0 >= $this->remaining) {
            return parent::send($data, true);
        }

        return parent::send($data, $end);
    }
}
