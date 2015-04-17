<?php
namespace Icicle\Http\Message;

class Encoder
{
    /**
     * @param   \Icicle\Http\Message\RequestInterface $request
     *
     * @return  string
     */
    public function encodeRequest(RequestInterface $request)
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
     * @param   \Icicle\Http\Message\ResponseInterface $response
     *
     * @return  string
     */
    public function encodeResponse(ResponseInterface $response)
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
     * @param   string[][] $headers
     *
     * @return  string
     */
    protected function encodeHeaders(array $headers)
    {
        $data = '';

        foreach ($headers as $name => $values) {
            switch (strtolower($name)) {
                case 'set-cookie':
                    foreach ($values as $value) {
                        $data .= sprintf("%s: %s\r\n", $name, $value);
                    }
                    break;

                default:
                    $data .= sprintf("%s: %s\r\n", $name, implode(',', $values));
            }
        }

        return $data;
    }
}
