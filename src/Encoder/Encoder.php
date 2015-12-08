<?php
namespace Icicle\Http\Encoder;

use Icicle\Http\Message\Request;
use Icicle\Http\Message\Response;

interface Encoder
{
    /**
     * @param \Icicle\Http\Message\Response $response
     *
     * @return string
     */
    public function encodeResponse(Response $response);

    /**
     * @param \Icicle\Http\Message\Request $request
     *
     * @return string
     */
    public function encodeRequest(Request $request);
}
