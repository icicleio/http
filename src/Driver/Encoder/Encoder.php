<?php
namespace Icicle\Http\Driver\Encoder;

use Icicle\Http\Message\{Request, Response};

interface Encoder
{
    /**
     * @param \Icicle\Http\Message\Response $response
     *
     * @return string
     */
    public function encodeResponse(Response $response): string;

    /**
     * @param \Icicle\Http\Message\Request $request
     *
     * @return string
     */
    public function encodeRequest(Request $request): string;
}
