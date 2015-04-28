<?php
namespace Icicle\Http\Encoder;

use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\ResponseInterface;

interface EncoderInterface
{
    /**
     * @param   \Icicle\Http\Message\ResponseInterface $response
     *
     * @return  string
     */
    public function encodeResponse(ResponseInterface $response);

    /**
     * @param   \Icicle\Http\Message\RequestInterface $request
     *
     * @return  string
     */
    public function encodeRequest(RequestInterface $request);
}
