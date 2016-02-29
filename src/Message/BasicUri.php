<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\InvalidValueException;

/**
 * Uri implementation loosely based on PSR-7.
 */
class BasicUri implements Uri
{
    /**
     * Array of schemes to corresponding port numbers.
     *
     * @var int[]
     */
    private static $schemes = [
        'ftp' => 21,
        'ssh' => 22,
        'http'  => 80,
        'https' => 443,
        'ws' => 80,
        'wss' => 443,
    ];

    /**
     * @var string
     */
    private $scheme;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int|null
     */
    private $port;

    /**
     * @var string
     */
    private $user;

    /**
     * @var string|null
     */
    private $password;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string[][]
     */
    private $query = [];

    /**
     * @var string
     */
    private $fragment;

    /**
     * @param string $uri
     *
     * @throws \Icicle\Http\Exception\InvalidValueException
     */
    public function __construct($uri = '')
    {
        $this->parseUri((string) $uri);
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * {@inheritdoc}
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort(): int
    {
        if (0 === $this->port) {
            return $this->getPortForScheme();
        }

        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryValues(): array
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function hasQueryValue(string $name): bool
    {
        return isset($this->query[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryValue(string $name): string
    {
        return isset($this->query[$name][0]) ? $this->query[$name][0] : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryValueAsArray(string $name): array
    {
        return isset($this->query[$name]) ? $this->query[$name] : [];
    }

    /**
     * {@inheritdoc}
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * {@inheritdoc}
     */
    public function withScheme(string $scheme = null): Uri
    {
        $new = clone $this;
        $new->scheme = $new->filterScheme($scheme);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withUser(string $user, string $password = null): Uri
    {
        $new = clone $this;

        $new->user = decode($user);
        $new->password = decode((string) $password);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHost(string $host = null): Uri
    {
        $new = clone $this;
        $new->host = (string) $host;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withPort(int $port = null): Uri
    {
        $new = clone $this;
        $new->port = $new->filterPort($port);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withPath(string $path = null): Uri
    {
        $new = clone $this;
        $new->path = $new->parsePath($path);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withQuery(string $query = null): Uri
    {
        $new = clone $this;
        $new->query = $new->parseQuery($query);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withFragment(string $fragment = null): Uri
    {
        $new = clone $this;
        $new->fragment = $new->parseFragment($fragment);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withQueryValue(string $name, $value): Uri
    {
        $new = clone $this;

        $new->query[$name] = $this->filterValue($value);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedQueryValue(string $name, $value): Uri
    {
        $new = clone $this;

        if (isset($new->query[$name])) {
            $new->query[$name][] = $value;
        } else {
            $new->query[$name] = [$value];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withoutQueryValue(string $name): Uri
    {
        $new = clone $this;

        unset($new->query[$name]);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        $uri = $this->encodeAuthority();

        if (!empty($uri)) {
            $scheme = $this->getScheme();
            if ($scheme) {
                $uri = sprintf('%s://%s', $scheme, $uri);
            }
        }

        $uri .= $this->encodePath();

        $query = $this->encodeQuery();
        if ($query) {
            $uri = sprintf('%s?%s', $uri, $query);
        }

        if ($this->fragment) {
            $uri = sprintf('%s#%s', $uri, encode($this->fragment));
        }

        return $uri;
    }

    /**
     * Returns the default port for the current scheme or null if no scheme is set.
     *
     * @return int
     */
    protected function getPortForScheme(): int
    {
        $scheme = $this->getScheme();

        if (!$scheme) {
            return 0;
        }

        return self::$schemes[$scheme];
    }

    /**
     * @param string $uri
     *
     * @throws \Icicle\Http\Exception\InvalidValueException
     */
    private function parseUri($uri)
    {
        $components = parse_url($uri);

        if (!$components) {
            throw new InvalidValueException('Invalid URI.');
        }

        $this->scheme   = isset($components['scheme'])   ? $this->filterScheme($components['scheme']) : '';
        $this->host     = isset($components['host'])     ? $components['host'] : '';
        $this->port     = isset($components['port'])     ? $this->filterPort($components['port']) : 0;
        $this->user     = isset($components['user'])     ? decode($components['user']) : '';
        $this->password = isset($components['pass'])     ? decode($components['pass']) : '';
        $this->path     = isset($components['path'])     ? $this->parsePath($components['path']) : '';
        $this->query    = isset($components['query'])    ? $this->parseQuery($components['query']) : [];
        $this->fragment = isset($components['fragment']) ? $this->parseFragment($components['fragment']) : '';
    }

    /**
     * @param string $scheme
     *
     * @return string
     *
     * @throws \Icicle\Http\Exception\InvalidValueException
     */
    protected function filterScheme(string $scheme = null): string
    {
        if (null === $scheme) {
            return '';
        }

        $scheme = strtolower($scheme);
        $scheme = rtrim($scheme, ':/');

        return $scheme;
    }

    /**
     * @param int $port
     *
     * @return int
     *
     * @throws \Icicle\Http\Exception\InvalidValueException
     */
    protected function filterPort(int $port = null): int
    {
        $port = (int) $port; // Cast null to 0.

        if (0 > $port || 0xffff < $port) {
            throw new InvalidValueException(
                sprintf('Invalid port: %d. Must be 0 or an integer between 1 and 65535.', $port)
            );
        }

        return $port;
    }

    /**
     * @param string|null $path
     *
     * @return string
     */
    protected function parsePath(string $path = null): string
    {
        if ('' === $path || null === $path) {
            return '';
        }

        $path = ltrim($path, '/');

        $path = '*' === $path ? $path : '/' . $path;

        return decode($path);
    }

    /**
     * @param string|null $query
     *
     * @return string[]
     */
    protected function parseQuery(string $query = null): array
    {
        $query = ltrim($query, '?');

        $fields = [];

        foreach (explode('&', $query) as $data) {
            list($name, $value) = $this->parseQueryPair($data);
            if ('' !== $name) {
                if (isset($fields[$name])) {
                    $fields[$name][] = $value;
                } else {
                    $fields[$name] = [$value];
                }
            }
        }

        ksort($fields);

        return $fields;
    }

    /**
     * @param string $data
     *
     * @return array
     */
    protected function parseQueryPair(string $data): array
    {
        $data = explode('=', $data, 2);
        if (1 === count($data)) {
            $data[] = '';
        }

        return array_map(__NAMESPACE__ . '\decode', $data);
    }

    /**
     * @param string $fragment
     *
     * @return string
     */
    protected function parseFragment(string $fragment = null): string
    {
        $fragment = ltrim($fragment, '#');

        return decode($fragment);
    }

    /**
     * Converts a given query value to an integer-indexed array of strings.
     *
     * @param mixed|mixed[] $values
     *
     * @return string[]
     *
     * @throws \Icicle\Http\Exception\InvalidValueException If the given value cannot be converted to a string and
     *     is not an array of values that can be converted to strings.
     */
    protected function filterValue($values): array
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $lines = [];

        foreach ($values as $value) {
            if (is_numeric($value) || is_null($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $value = (string) $value;
            } elseif (!is_string($value)) {
                throw new InvalidValueException('Query values must be strings or an array of strings.');
            }

            $lines[] = decode($value);
        }

        return $lines;
    }

    protected function encodeAuthority(): string
    {
        $authority = $this->getHost();
        if (!$authority) {
            return '';
        }

        if ('' !== $this->user) {
            if ('' !== $this->password) {
                $authority = sprintf('%s:%s@%s', encode($this->user), encode($this->password), $authority);
            } else {
                $authority = sprintf('%s@%s', encode($this->user), $authority);
            }
        }

        $port = $this->getPort();
        if ($port && $this->getPortForScheme() !== $port) {
            $authority = sprintf('%s:%s', $authority, $this->getPort());
        }

        return $authority;
    }

    protected function encodeQuery(): string
    {
        if (empty($this->query)) {
            return '';
        }

        $query = [];

        foreach ($this->query as $name => $values) {
            foreach ($values as $value) {
                if ('' === $value) {
                    $query[] = encode($name);
                } else {
                    $query[] = sprintf('%s=%s', encode($name), encode($value));
                }
            }
        }

        return implode('&', $query);
    }

    protected function encodePath(): string
    {
        return preg_replace_callback(
            '/(?:[^A-Za-z0-9_\-\.~\/:%]+|%(?![A-Fa-f0-9]{2}))/',
            function (array $matches) {
                return rawurlencode($matches[0]);
            },
            $this->path
        );
    }
}
