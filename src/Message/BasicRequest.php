<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\InvalidMethodException;
use Icicle\Http\Exception\InvalidValueException;
use Icicle\Http\Message\Cookie\BasicCookie;
use Icicle\Http\Message\Cookie\Cookie;
use Icicle\Stream\ReadableStream;

class BasicRequest extends AbstractMessage implements Request
{
    /**
     * @var string
     */
    private $method;

    /**
     * @var \Icicle\Http\Message\Uri
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
     * @var \Icicle\Http\Message\Cookie\Cookie[]
     */
    private $cookies = [];

    /**
     * @param string $method
     * @param string|\Icicle\Http\Message\Uri $uri
     * @param \Icicle\Stream\ReadableStream|null $stream
     * @param string[][] $headers
     * @param string $target
     * @param string $protocol
     *
     * @throws \Icicle\Http\Exception\MessageException If one of the arguments is invalid.
     */
    public function __construct(
        string $method,
        $uri = '',
        array $headers = [],
        ReadableStream $stream = null,
        string $target = null,
        string $protocol = '1.1'
    ) {
        parent::__construct($headers, $stream, $protocol);

        $this->method = $this->filterMethod($method);
        $this->uri = $uri instanceof Uri ? $uri : new BasicUri($uri);

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
    public function getRequestTarget(): string
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
    public function getUri(): Uri
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function withRequestTarget(string $target = null): Request
    {
        $new = clone $this;
        $new->target = $new->filterTarget($target);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod(string $method): Request
    {
        $new = clone $this;
        $new->method = $new->filterMethod($method);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader(string $name, $value): Message
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
    public function withAddedHeader(string $name, $value): Message
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
    public function withoutHeader(string $name): Message
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
    public function withUri($uri): Request
    {
        if (!$uri instanceof Uri) {
            $uri = new BasicUri($uri);
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
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookie(string $name)
    {
        $name = (string) $name;
        return array_key_exists($name, $this->cookies) ? $this->cookies[$name] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCookie(string $name): bool
    {
        return array_key_exists((string) $name, $this->cookies);
    }

    /**
     * {@inheritdoc}
     */
    public function withCookie(string $name, $value): Request
    {
        $new = clone $this;
        $new->cookies[(string) $name] = new BasicCookie($name, $value);
        $new->setHeadersFromCookies();
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutCookie(string $name): Request
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
    protected function filterMethod(string $method): string
    {
        if (!is_string($method)) {
            throw new InvalidMethodException('Request method must be a string.');
        }

        return strtoupper($method);
    }

    /**
     * @param string|null $target
     *
     * @return string
     *
     * @throws \Icicle\Http\Exception\InvalidValueException If the target contains whitespace.
     */
    protected function filterTarget(string $target = null): string
    {
        if (null === $target) {
            return '';
        }

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

        $headers = $this->getHeaderAsArray('Cookie');

        foreach ($headers as $line) {
            foreach (explode(';', $line) as $pair) {
                $cookie = BasicCookie::fromHeader($pair);
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
