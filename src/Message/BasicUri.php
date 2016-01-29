<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\InvalidValueException;

/**
 * Uri implementation loosely based on PSR-7.
 */
class BasicUri implements Uri
{
    const UNRESERVED_CHARS = 'A-Za-z0-9_\-\.~';
    const GEN_DELIMITERS = ':\/\?#@';
    const SUB_DELIMITERS = '!\$&\'\(\)\[\]\*\+,;=';
    const ENCODED_CHAR = '%(?![A-Fa-f0-9]{2})';

    /**
     * Array of valid schemes to corresponding port numbers.
     *
     * @var int[]
     */
    private static $schemes = [
        'http'  => 80,
        'https' => 443,
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
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * {@inheritdoc}
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort()
    {
        if (0 === $this->port) {
            return $this->getPortForScheme();
        }

        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryValues()
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function hasQueryValue($name)
    {
        return isset($this->query[$this->encodeValue($name)]);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryValue($name)
    {
        return isset($this->query[$name][0]) ? $this->query[$name][0] : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryValueAsArray($name)
    {
        return isset($this->query[$name]) ? $this->query[$name] : [];
    }

    /**
     * {@inheritdoc}
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     * {@inheritdoc}
     */
    public function withScheme($scheme = null)
    {
        $new = clone $this;
        $new->scheme = $new->filterScheme($scheme);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withUser($user, $password = null)
    {
        $new = clone $this;

        $new->user = $new->decodeValue($user);
        $new->password = $new->decodeValue($password);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHost($host = null)
    {
        $new = clone $this;
        $new->host = (string) $host;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withPort($port = null)
    {
        $new = clone $this;
        $new->port = $new->filterPort($port);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withPath($path = null)
    {
        $new = clone $this;
        $new->path = $new->parsePath($path);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withQuery($query = null)
    {
        $new = clone $this;
        $new->query = $new->parseQuery($query);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withFragment($fragment = null)
    {
        $new = clone $this;
        $new->fragment = $new->parseFragment($fragment);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withQueryValue($name, $value)
    {
        $new = clone $this;

        $name = $new->decodeValue((string) $name);

        $new->query[$name] = $this->filterValue($value);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedQueryValue($name, $value)
    {
        $new = clone $this;

        $name = $new->decodeValue($name);
        $value = $new->decodeValue($value);

        if (isset($new->query[$name])) {
            $new->query[$name][] = $value;
        } else {
            $new->query[$name] = [$value];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withoutQueryValue($name)
    {
        $new = clone $this;

        $name = $this->decodeValue($name);

        unset($new->query[$name]);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
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
            $uri = sprintf('%s#%s', $uri, $this->encodeValue($this->fragment));
        }

        return $uri;
    }

    /**
     * Returns the default port for the current scheme or null if no scheme is set.
     *
     * @return int|null
     */
    protected function getPortForScheme()
    {
        $scheme = $this->getScheme();

        if (!$scheme) {
            return 0;
        }

        return $this->allowedSchemes()[$scheme];
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
        $this->user     = isset($components['user'])     ? $components['user'] : '';
        $this->password = isset($components['pass'])     ? $components['pass'] : '';
        $this->path     = isset($components['path'])     ? $this->parsePath($components['path']) : '';
        $this->query    = isset($components['query'])    ? $this->parseQuery($components['query']) : [];
        $this->fragment = isset($components['fragment']) ? $this->parseFragment($components['fragment']) : '';
    }

    /**
     * @return int[] Array indexed by valid scheme names to their corresponding ports.
     */
    protected function allowedSchemes()
    {
        return self::$schemes;
    }

    /**
     * @param string $scheme
     *
     * @return string
     *
     * @throws \Icicle\Http\Exception\InvalidValueException
     */
    protected function filterScheme($scheme)
    {
        if (null === $scheme) {
            return '';
        }

        $scheme = strtolower($scheme);
        $scheme = rtrim($scheme, ':/');

        if ('' !== $scheme && !array_key_exists($scheme, $this->allowedSchemes())) {
            throw new InvalidValueException(sprintf(
                    'Invalid scheme: %s. Must be null, an empty string, or in set (%s).',
                    $scheme,
                    implode(', ', array_keys($this->allowedSchemes()))
                ));
        }

        return $scheme;
    }

    /**
     * @param int $port
     *
     * @return int
     *
     * @throws \Icicle\Http\Exception\InvalidValueException
     */
    protected function filterPort($port)
    {
        $port = (int) $port;

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
    protected function parsePath($path)
    {
        if ('' === $path || null === $path) {
            return '';
        }

        $path = ltrim($path, '/');

        $path = '/' . $path;

        return $this->decodeValue($path);
    }

    /**
     * @param string|null $query
     *
     * @return string[]
     */
    protected function parseQuery($query)
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
     * @return string
     */
    protected function parseQueryPair($data)
    {
        $data = explode('=', $data, 2);
        if (1 === count($data)) {
            $data[] = '';
            //return [$this->decodeValue($data[0]), ''];
        }

        return array_map([$this, 'decodeValue'], $data);

        //return [$this->decodeValue($data[0]), $this->decodeValue($data[1])];
    }

    /**
     * @param string $fragment
     *
     * @return string
     */
    protected function parseFragment($fragment)
    {
        $fragment = ltrim($fragment, '#');

        return $this->decodeValue($fragment);
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
    protected function filterValue($values)
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

            $lines[] = $this->decodeValue($value);
        }

        return $lines;
    }

    /**
     * {@inheritdoc}
     */
    public function encodeAuthority()
    {
        $authority = $this->getHost();
        if (!$authority) {
            return '';
        }

        if ('' !== $this->user) {
            if ('' !== $this->password) {
                $user = sprintf('%s:%s', $this->encodeValue($this->user), $this->encodeValue($this->password));
            } else {
                $user = $this->encodeValue($this->user);
            }

            $authority = sprintf('%s@%s', $user, $authority);
        }

        $port = $this->getPort();
        if ($port && $this->getPortForScheme() !== $port) {
            $authority = sprintf('%s:%s', $authority, $this->getPort());
        }

        return $authority;
    }

    /**
     * {@inheritdoc}
     */
    public function encodeQuery()
    {
        if (empty($this->query)) {
            return '';
        }

        $query = [];

        foreach ($this->query as $name => $values) {
            foreach ($values as $value) {
                $name = $this->encodeValue($name);
                $value = $this->encodeValue($value);

                if ('' === $value) {
                    $query[] = $name;
                } else {
                    $query[] = sprintf('%s=%s', $this->encodeValue($name), $value);
                }
            }
        }

        return implode('&', $query);
    }

    /**
     * {@inheritdoc}
     */
    public function encodeFragment()
    {
        return $this->encodeValue($this->fragment);
    }

    /**
     * {@inheritdoc}
     */
    public function encodePath()
    {
        return preg_replace_callback(
            '/(?:[^' . self::UNRESERVED_CHARS . '\/%]+|' . self::ENCODED_CHAR . ')/',
            function (array $matches) {
                return rawurlencode($matches[0]);
            },
            $this->path
        );
    }

    /**
     * Escapes all reserved chars.
     *
     * @param string $string
     *
     * @return string
     */
    protected function encodeValue($string)
    {
        return preg_replace_callback(
            '/(?:[^' . self::UNRESERVED_CHARS . self::SUB_DELIMITERS . '\/%]+|' . self::ENCODED_CHAR . ')/',
            function (array $matches) {
                return rawurlencode($matches[0]);
            },
            $string
        );
    }

    /**
     * Decodes all URL encoded characters.
     *
     * @param string $string
     *
     * @return string
     */
    protected function decodeValue($string)
    {
        return rawurldecode($string);
    }
}
