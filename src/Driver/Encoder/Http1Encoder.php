<?php
namespace Icicle\Http\Driver\Encoder;

use Icicle\Http\Message;
use Icicle\Http\Message\{Request, Response};

class Http1Encoder
{
    /**
     * {@inheritdoc}
     */
    public function encodeResponse(Response $response): string
    {
        return sprintf(
            "HTTP/%s %d %s\r\n%s\r\n",
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $this->encodeHeaders($response->getHeaders())
        );
    }

    /**
     * {@inheritdoc}
     */
    public function encodeRequest(Request $request): string
    {
        return sprintf(
            "%s %s HTTP/%s\r\n%s\r\n",
            $request->getMethod(),
            $request->getRequestTarget(),
            $request->getProtocolVersion(),
            $this->encodeHeaders($request->getHeaders())
        );
    }

    /**
     * @param string[][] $headers
     *
     * @return string
     */
    protected function encodeHeaders(array $headers): string
    {
        $data = '';

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                switch (strtolower($name)) {
                    case 'host':
                        $data = sprintf("%s: %s\r\n%s", $name, Message\encodeHeader($value), $data);
                        break;

                    default:
                        $data .= sprintf("%s: %s\r\n", $name, Message\encodeHeader($value));
                }
            }
        }

        return $data;
    }
}
