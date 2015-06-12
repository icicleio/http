<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\InvalidStatusException;
use Icicle\Stream\ReadableStreamInterface;

class Response extends Message implements ResponseInterface
{
    /**
     * Map of status codes to reason phrases.
     *
     * @var string[]
     */
    private static $phrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * @var int
     */
    private $status;

    /**
     * @var string|null
     */
    private $reason;

    /**
     * @param int $code Status code.
     * @param \Icicle\Stream\ReadableStreamInterface|null $stream
     * @param string[][] $headers
     * @param string|null $reason Status code reason.
     * @param string $protocol
     *
     * @throws \Icicle\Http\Exception\InvalidArgumentException If one of the arguments is invalid.
     */
    public function __construct(
        $code = 200,
        array $headers = null,
        ReadableStreamInterface $stream = null,
        $reason = null,
        $protocol = '1.1'
    ) {
        parent::__construct($headers, $stream, $protocol);

        $this->status = $this->validateStatusCode($code);
        $this->reason = $this->filterReason($reason);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase()
    {
        if (null !== $this->reason) {
            return $this->reason;
        }

        return isset(self::$phrases[$this->status]) ? self::$phrases[$this->status] : '';
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus($code, $reason = null)
    {
        $new = clone $this;
        $new->status = $new->validateStatusCode($code);
        $new->reason = $new->filterReason($reason);
        return $new;
    }

    /**
     * @param string|int $code
     *
     * @return int
     *
     * @throws \Icicle\Http\Exception\InvalidStatusException
     */
    protected function validateStatusCode($code)
    {
        if (!is_numeric($code) || is_float($code) || 100 > $code || 599 < $code) {
            throw new InvalidStatusException(
                'Invalid status code. Must be an integer between 100 and 599, inclusive.'
            );
        }

        return (int) $code;
    }

    /**
     * @param string $reason
     *
     * @return string|null
     */
    protected function filterReason($reason)
    {
        return $reason ? (string) $reason : null;
    }
}
