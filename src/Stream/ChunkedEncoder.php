<?php
namespace Icicle\Http\Stream;

use Icicle\Stream\Stream;

class ChunkedEncoder extends Stream
{
    /**
     * @param   string $data
     * @param   bool $end
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    protected function send($data, $end = false)
    {
        if (strlen($data)) {
            $data = $this->encode($data);
        }

        if ($end) {
            $data .= $this->encode('');
        }

        return parent::send($data, $end);
    }

    /**
     * @param   string $data
     *
     * @return  string
     */
    protected function encode($data)
    {
        return sprintf("%x\r\n%s\r\n", strlen($data), $data);
    }
}
