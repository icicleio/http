<?php
namespace Icicle\Http\Stream;

use Icicle\Stream\MemoryStream;

class ChunkedEncoder extends MemoryStream
{
    /**
     * {@inheritdoc}
     */
    protected function send(string $data, float $timeout = 0, bool $end = false): \Generator
    {
        $length = strlen($data);

        if ($length) {
            $data = sprintf("%x\r\n%s\r\n", $length, $data);
        }

        if ($end) {
            $data .= "0\r\n\r\n";
        }

        return parent::send($data, $timeout, $end);
    }
}
