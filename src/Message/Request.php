<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\InvalidMethodException;
use Icicle\Http\Exception\InvalidValueException;
use Icicle\Http\Message\Cookie\Cookie;
use Icicle\Stream\ReadableStreamInterface;

class Request extends Message implements RequestInterface
{
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
     * @var string
     */
    private $target;

    /**
     * @var \Icicle\Http\Message\Cookie\CookieInterface[]
     */
    private $cookies = [];

    /**
     * @param string $method
     * @param string|\Icicle\Http\Message\UriInterface $uri
     * @param \Icicle\Stream\ReadableStreamInterface|null $stream
     * @param string[][] $headers
     * @param string $target
     * @param string $protocol
     *
     * @throws \Icicle\Http\Exception\MessageException If one of the arguments is invalid.
     */
    public function __construct(
        $method,
        $uri = '',
        array $headers = [],
        ReadableStreamInterface $stream = null,
        $target = '',
        $protocol = '1.1'
    ) {
        parent::__construct($headers, $stream, $protocol);

        $this->method = $this->filterMethod($method);
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri($uri);

        $this->target = $this->filterTarget($target);

        if (!$this->hasHeader('Host')) {
            $this->setHostFromUri();
        }

        if ($this->hasHeader('Cookie')) {
            $this->setCookiesFromHeaders();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestTarget()
    {
        if ('' !== $this->target) {
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
     * {@inheritdoc}
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function withRequestTarget($target)
    {
        $new = clone $this;
        $new->target = $new->filterTarget($target);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod($method)
    {
        $new = clone $this;
        $new->method = $new->filterMethod($method);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader($name, $value)
    {
        $new = parent::withHeader($name, $value);

        $normalized = strtolower($name);

        if ('host' === $normalized) {
            $new->hostFromUri = false;
        } elseif ('cookie' === $normalized) {
            $new->setCookiesFromHeaders();
        }

        return $new;
    }
    /**
     * {@inheritdoc}
     */
    public function withAddedHeader($name, $value)
    {
        $normalized = strtolower($name);

        if ('host' === $normalized && $this->hostFromUri) {
            $new = parent::withoutHeader('Host');
            $new->setHeader($name, $value);
            $new->hostFromUri = false;
        } else {
            $new = parent::withAddedHeader($name, $value);

            if ('cookie' === $normalized) {
                $new->setCookiesFromHeaders();
            }
        }

        return $new;
    }
    /**
     * {@inheritdoc}
     */
    public function withoutHeader($name)
    {
        $new = parent::withoutHeader($name);

        $normalized = strtolower($name);

        if ('host' === $normalized) {
            $new->setHostFromUri();
        } elseif ('cookie' === $normalized) {
            $new->cookies = [];
        }

        return $new;
    }
    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getCookies()
    {
        return array_values($this->cookies);
    }

    /**
     * {@inheritdoc}
     */
    public function getCookie($name)
    {
        $name = (string) $name;
        return array_key_exists($name, $this->cookies) ? $this->cookies[$name] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCookie($name)
    {
        return array_key_exists((string) $name, $this->cookies);
    }

    /**
     * {@inheritdoc}
     */
    public function withCookie($name, $value)
    {
        $new = clone $this;
        $new->cookies[(string) $name] = new Cookie($name, $value);
        $new->setHeadersFromCookies();
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutCookie($name)
    {
        $new = clone $this;
        unset($new->cookies[(string) $name]);
        $new->setHeadersFromCookies();
        return $new;
    }

    /**
     * @param string $method
     *
     * @return string
     *
     * @throws \Icicle\Http\Exception\InvalidMethodException If the method is not valid.
     */
    protected function filterMethod($method)
    {
        if (!is_string($method)) {
            throw new InvalidMethodException('Request method must be a string.');
        }

        return strtoupper($method);
    }

    /**
     * @param string $target
     *
     * @return string
     *
     * @throws \Icicle\Http\Exception\InvalidValueException If the target contains whitespace.
     */
    protected function filterTarget($target)
    {
        if (!is_string($target)) {
            throw new InvalidMethodException('Request target must be a string.');
        }

        if (preg_match('/\s/', $target)) {
            throw new InvalidValueException('Request target cannot contain whitespace.');
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

            parent::setHeader('Host', $host);
        }
    }

    /**
     * Sets cookies based on headers.
     *
     * @throws \Icicle\Http\Exception\InvalidValueException
     */
    private function setCookiesFromHeaders()
    {
        $this->cookies = [];

        $headers = $this->getHeader('Cookie');

        foreach ($headers as $line) {
            foreach (explode(';', $line) as $pair) {
                $cookie = Cookie::fromHeader($pair);
                $this->cookies[$cookie->getName()] = $cookie;
            }
        }
    }

    /**
     * Sets headers based on cookie values.
     */
    private function setHeadersFromCookies()
    {
        $values = [];

        foreach ($this->cookies as $cookie) {
            $values[] = $cookie->toHeader();
        }

        if (!empty($values)) {
            $this->setHeader('Cookie', implode('; ', $values));
        }
    }
}
