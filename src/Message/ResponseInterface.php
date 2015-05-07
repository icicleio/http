<?php
namespace Icicle\Http\Message;

interface ResponseInterface extends MessageInterface
{
    /**
     * Returns the response status code.
     *
     * @return  int
     */
    public function getStatusCode();

    /**
     * Returns the reason phrase describing the status code.
     *
     * @return  string
     */
    public function getReasonPhrase();

    /**
     * Returns a new instance with the given status.
     *
     * @param   int $code 3-digit status code.
     * @param   string|null $reason Description of status code or null to use default reason associated with the
     *          status code given.
     *
     * @return  static
     */
    public function withStatus($code, $reason = null);
}
