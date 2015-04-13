<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\InvalidArgumentException;
use Icicle\Stream\ReadableStreamInterface;

class Request extends Message implements RequestInterface
{
    /**
     * @var string[]
     */
    private static $methods = [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'OPTIONS',
        'HEAD',
        'CONNECT',
        'PATCH',
        'TRACE',
    ];

    /**
     * @var string
     */
    private $method;

    /**
     * @var \Icicle\Http\Message\UriInterface
     */
    private $uri;

    /**
     * @var bool
     */
    private $hostFromUri = false;

    /**
     * @var string|null
     */
    private $target;

    /**
     * @param   string $method
     * @param   string|\Icicle\Http\Message\UriInterface $uri
     * @param   \Icicle\Stream\ReadableStreamInterface|null $stream
     * @param   string[]|null $headers
     * @param   string|null $target
     * @param   string $protocol
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If one of the arguments is invalid.
     */
    public function __construct(
        $method,
        $uri = '',
        ReadableStreamInterface $stream = null,
        array $headers = null,
        $target = null,
        $protocol = '1.1'
    ) {
        parent::__construct($stream, $headers, $protocol);

        $this->method = $this->filterMethod($method);
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri($uri);

        if (null !== $target) {
            $this->target = $this->filterTarget($target);
        }

        if (!$this->hasHeader('Host')) {
            $this->setHostFromUri();
        }
    }

    /**
     * @inheritdoc
     */
    public function getRequestTarget()
    {
        if (null !== $this->target) {
            return $this->target;
        }

        $target = $this->uri->getPath();

        if ('' === $target) {
            $target = '/';
        }

        $query = $this->uri->getQuery();
        if ('' !== $query) {
            $target = sprintf('%s?%s', $target, $query);
        }

        return $target;
    }

    /**
     * @inheritdoc
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @inheritdoc
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @inheritdoc
     */
    public function withRequestTarget($target)
    {
        $new = clone $this;
        $new->target = $new->filterTarget($target);
        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withMethod($method)
    {
        $new = clone $this;
        $new->method = $new->filterMethod($method);
        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withHeader($name, $value)
    {
        $new = parent::withHeader($name, $value);

        if (strtolower($name) === 'host') {
            $new->hostFromUri = false;
        }

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withAddedHeader($name, $value)
    {
        if (strtolower($name) === 'host' && $this->hostFromUri) {
            $new = parent::withoutHeader('Host');
            $new->setHeader($name, $value);
            $new->hostFromUri = false;
        } else {
            $new = parent::withAddedHeader($name, $value);
        }

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withoutHeader($name)
    {
        $new = parent::withoutHeader($name);

        if (strtolower($name) === 'host') {
            $new->setHostFromUri();
        }

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withUri($uri)
    {
        if (!$uri instanceof UriInterface) {
            $uri = new Uri($uri);
        }

        $new = clone $this;
        $new->uri = $uri;

        if ($new->hostFromUri) {
            $new->setHostFromUri();
        }

        return $new;
    }

    /**
     * Returns an array of allowed methods.
     *
     * @return  string[]
     */
    protected function allowedMethods()
    {
        return self::$methods;
    }

    /**
     * @param   string $method
     *
     * @return  string
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If the method is not valid.
     */
    protected function filterMethod($method)
    {
        if (!is_string($method)) {
            throw new InvalidArgumentException('Request method must be a string.');
        }

        $method = strtoupper($method);

        if (!in_array($method, $this->allowedMethods(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported request method. Must be in the set (%s)',
                implode(', ', $this->allowedMethods())
            ));
        }

        return $method;
    }

    /**
     * @param   string $target
     *
     * @return  string
     *
     * @throws  \Icicle\Http\Exception\InvalidArgumentException If the target contains whitespace.
     */
    protected function filterTarget($target)
    {
        if (preg_match('/\s/', $target)) {
            throw new InvalidArgumentException('Request target cannot contain whitespace.');
        }

        return $target;
    }

    /**
     * Sets the host based on the current URI.
     */
    private function setHostFromUri()
    {
        $this->hostFromUri = true;

        $host = $this->uri->getHost();

        if (!empty($host)) { // Do not set Host header if URI has no host.
            $port = $this->uri->getPort();
            if (null !== $port) {
                $host = sprintf('%s:%d', $host, $port);
            }

            $this->setHeader('Host', $host);
        }
    }
}
