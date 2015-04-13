<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\InvalidArgumentException;

/**
 * URI implementation based on phly/http URI implementation.
 *
 * @see https://github.com/phly/http
 */
class Uri implements UriInterface
{
    const UNRESERVED_CHARS = 'A-Za-z0-9_\-\.~';
    const GEN_DELIMITERS = ':\/\?#\[\]@';
    const SUB_DELIMITERS = '!\$&\'\(\)\*\+,;=';
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
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string[]
     */
    private $query = [];

    /**
     * @var string
     */
    private $fragment;

    /**
     * @param   string $uri
     */
    public function __construct($uri = '')
    {
        $this->parseUri((string) $uri);
    }

    /**
     * @inheritdoc
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * @inheritdoc
     */
    public function getAuthority()
    {
        $authority = $this->getHost();
        if (!$authority) {
            return '';
        }

        $userInfo = $this->getUserInfo();
        if ($userInfo) {
            $authority = sprintf('%s@%s', $userInfo, $authority);
        }

        $port = $this->getPort();
        if ($port && $this->getPortForScheme() !== $port) {
            $authority = sprintf('%s:%s', $authority, $this->getPort());
        }

        return $authority;
    }

    /**
     * @inheritdoc
     */
    public function getUserInfo()
    {
        if ($this->password) {
            return sprintf('%s:%s', $this->user, $this->password);
        }

        return $this->user;
    }

    /**
     * @inheritdoc
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @inheritdoc
     */
    public function getPort()
    {
        if (null === $this->port) {
            return $this->getPortForScheme();
        }

        return $this->port;
    }

    /**
     * @inheritdoc
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @inheritdoc
     */
    public function getQuery()
    {
        if (empty($this->query)) {
            return '';
        }

        $query = [];

        foreach ($this->query as $name => $value) {
            if ('' === $value) {
                $query[] = $name;
            } else {
                $query[] = sprintf('%s=%s', $name, $value);
            }
        }

        return implode('&', $query);
    }

    /**
     * @inheritdoc
     */
    public function getQueryValues()
    {
        return $this->query;
    }

    /**
     * @inheritdoc
     */
    public function getQueryValue($name)
    {
        $name = $this->encodeValue($name);

        return isset($this->query[$name]) ? $this->query[$name] : null;
    }

    /**
     * @inheritdoc
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     * @inheritdoc
     */
    public function withScheme($scheme)
    {
        $new = clone $this;
        $new->scheme = $new->filterScheme($scheme);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withUserInfo($user, $password = null)
    {
        $new = clone $this;

        $new->user = $new->encodeValue($user);

        if (null === $password) {
            $new->password = null;
        } else {
            $new->password = $new->encodeValue($password);
        }

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withHost($host)
    {
        $new = clone $this;
        $new->host = (string) $host;

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withPort($port)
    {
        $new = clone $this;
        $new->port = $new->filterPort($port);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withPath($path)
    {
        $new = clone $this;
        $new->path = $new->parsePath($path);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withQuery($query)
    {
        $new = clone $this;
        $new->query = $new->parseQuery($query);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withFragment($fragment)
    {
        $new = clone $this;
        $new->fragment = $new->parseFragment($fragment);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withQueryValue($name, $value)
    {
        $new = clone $this;

        $name = $new->encodeValue($name);
        $value = $new->encodeValue($value);

        $new->query[$name] = $value;

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withoutQueryValue($name)
    {
        $new = clone $this;

        $name = $this->encodeValue($name);

        unset($new->query[$name]);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        $uri = $this->getAuthority();

        if (!empty($uri)) {
            $scheme = $this->getScheme();
            if ($scheme) {
                $uri = sprintf('%s://%s', $scheme, $uri);
            }
        }

        $uri .= $this->getPath();

        $query = $this->getQuery();
        if ($query) {
            $uri = sprintf('%s?%s', $uri, $query);
        }

        $fragment = $this->getFragment();
        if ($fragment) {
            $uri = sprintf('%s#%s', $uri, $fragment);
        }

        return $uri;
    }

    /**
     * Returns the default port for the current scheme or null if no scheme is set.
     *
     * @return  int|null
     */
    protected function getPortForScheme()
    {
        $scheme = $this->getScheme();

        if (!$scheme) {
            return null;
        }

        return $this->allowedSchemes()[$scheme];
    }

    /**
     * @param   string $uri
     */
    private function parseUri($uri)
    {
        $components = parse_url($uri);

        $this->scheme   = isset($components['scheme'])   ? $this->filterScheme($components['scheme']) : '';
        $this->host     = isset($components['host'])     ? $components['host'] : '';
        $this->port     = isset($components['port'])     ? $this->filterPort($components['port']) : null;
        $this->user     = isset($components['user'])     ? $this->encodeValue($components['user']) : '';
        $this->password = isset($components['pass'])     ? $this->encodeValue($components['pass']) : '';
        $this->path     = isset($components['path'])     ? $this->parsePath($components['path']) : '';
        $this->query    = isset($components['query'])    ? $this->parseQuery($components['query']) : [];
        $this->fragment = isset($components['fragment']) ? $this->parseFragment($components['fragment']) : '';
    }

    /**
     * @return  int[] Array indexed by valid scheme names to their corresponding ports.
     */
    protected function allowedSchemes()
    {
        return self::$schemes;
    }

    /**
     * @param   string $scheme
     *
     * @return  string
     */
    protected function filterScheme($scheme)
    {
        if (null === $scheme) {
            return '';
        }

        $scheme = strtolower($scheme);
        $scheme = rtrim($scheme, ':/');

        if ('' !== $scheme && !array_key_exists($scheme, $this->allowedSchemes())) {
            throw new InvalidArgumentException(sprintf(
                    'Invalid scheme: %s. Must be null, an empty string, or in set (%s).',
                    $scheme,
                    implode(', ', array_keys($this->allowedSchemes()))
                ));
        }

        return $scheme;
    }

    /**
     * @param   int|null $port
     *
     * @return  int|null
     */
    protected function filterPort($port)
    {
        if (null !== $port) {
            $port = (int) $port;
            if (1 > $port || 0xffff < $port) {
                throw new InvalidArgumentException(
                    sprintf('Invalid port: %d. Must be null or an integer between 1 and 65535.', $port)
                );
            }
        }

        return $port;
    }

    /**
     * @param   string|null $path
     *
     * @return  string
     */
    protected function parsePath($path)
    {
        if ('' === $path || null === $path) {
            return '';
        }

        $path = ltrim($path, '/');

        $path = '/' . $path;

        return $this->encodePath($path);
    }

    /**
     * @param   string|null $query
     *
     * @return  string[]
     */
    protected function parseQuery($query)
    {
        $query = ltrim($query, '?');

        $fields = [];

        foreach (explode('&', $query) as $data) {
            list($name, $value) = $this->parseQueryPair($data);
            if ('' !== $name) {
                $fields[$name] = $value;
            }
        }

        ksort($fields);

        return $fields;
    }

    /**
     * @param   string $data
     *
     * @return  string
     */
    protected function parseQueryPair($data)
    {
        $data = explode('=', $data, 2);
        if (1 === count($data)) {
            return [$this->encodeValue($data[0]), ''];
        }
        return [$this->encodeValue($data[0]), $this->encodeValue($data[1])];
    }

    /**
     * @param   string $fragment
     *
     * @return  string
     */
    protected function parseFragment($fragment)
    {
        $fragment = ltrim($fragment, '#');

        return $this->encodeValue($fragment);
    }

    /**
     * Escapes all reserved chars and sub delimiters.
     *
     * @param   string $string
     *
     * @return  string
     */
    protected function encodePath($string)
    {
        return preg_replace_callback(
            '/(?:[^' . self::UNRESERVED_CHARS . '\/%]+|' . self::ENCODED_CHAR . ')/',
            function (array $matches) {
                return rawurlencode($matches[0]);
            },
            $string
        );
    }

    /**
     * Escapes all reserved chars.
     *
     * @param   string $string
     *
     * @return  string
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
}
