<?php
namespace Icicle\Http;

use Icicle\Http\Exception\InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    protected static $schemes = [
        'http'  => 80,
        'https' => 443
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
     * @var string
     */
    private $query;

    /**
     * @var string
     */
    private $fragment;

    /**
     * @var string|null
     */
    private $uri;

    /**
     * @param   string $uri
     */
    public function __construct($uri = '')
    {
        $uri = (string) $uri;

        if (strlen($uri)) {
            $this->parseUri($uri);
        }
    }

    /**
     * Nulls cached string representation of URI.
     */
    public function __clone()
    {
        $this->uri = null;
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
        if (!$this->host) {
            return $this->host;
        }

        $authority = $this->host;
        $userInfo = $this->getUserInfo();
        if ($userInfo) {
            $authority = sprintf('%s@%s', $userInfo, $authority);
        }

        if ($this->port) {
            $authority .= sprintf(':%s', $this->port);
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
        return $this->port;
    }

    /**
     * @inheritdoc
     */
    public function getPath()
    {
        $this->path;
    }

    /**
     * @inheritdoc
     */
    public function getQuery()
    {
        return $this->query;
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
        $scheme = $this->filterScheme($scheme);

        $new = clone $this;
        $new->scheme = $scheme;

        $new->port = $this->filterPort($this->port, $scheme);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withUserInfo($user, $password = null)
    {
        $new = clone $this;

        $new->user = $this->encode($user);

        if (null === $password) {
            $new->password = null;
        } else {
            $new->password = $this->encode($password);
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
        $port = $this->filterPort($port, $this->getScheme());

        $new = clone $this;
        $new->port = $port;

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withPath($path)
    {
        $new = clone $this;

        $new->path = $this->parsePath($path);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withQuery($query)
    {
        $query = $this->parseQuery($query);

        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withFragment($fragment)
    {
        $fragment = $this->parseFragment($fragment);

        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        if (null !== $this->uri) {
            return $this->uri;
        }

        $this->uri = '';

        $scheme = $this->getScheme();

        if ($scheme) {
            $this->uri .= sprintf('%s://', $scheme);
        }

        $this->uri .= $this->getAuthority();

        $this->uri .= $this->path;

        if ($this->query) {
            $this->uri .= sprintf('?%s', $this->query);
        }

        if ($this->fragment) {
            $this->uri .= sprintf('#%s', $this->fragment);
        }

        return $this->uri;
    }

    /**
     * @param   string $uri
     */
    private function parseUri($uri)
    {
        $components = parse_url($uri);

        $this->scheme   = isset($components['scheme'])  ? $this->filterScheme($components['scheme']) : '';
        $this->host     = isset($components['host'])    ? $components['host'] : '';
        $this->port     = isset($components['port'])    ? $this->filterPort($components['port'], $this->getScheme()) : null;
        $this->user     = isset($components['user'])    ? $this->encode($components['user']) : '';
        $this->password = isset($components['pass'])    ? $this->encode($components['pass']) : '';
        $this->path     = isset($components['path'])    ? $this->parsePath($components['path']) : '/';
        $this->query    = isset($components['query'])   ? $this->parseQuery($components['query']) : '';
        $this->fragment = isset($components['fragment'])? $this->parseFragment($components['fragment']) : '';
    }

    /**
     * @return  int[] Array indexed by valid scheme names to their corresponding ports.
     */
    protected function getValidSchemes()
    {
        return self::$schemes;
    }

    protected function filterScheme($scheme)
    {
        $scheme = strtolower($scheme);
        if (strpos($scheme, '://')) {
            str_replace('://', '', $scheme);
        }

        if (!array_key_exists($scheme, $this->getValidSchemes())) {
            throw new InvalidArgumentException("Invalid scheme: {$scheme}");
        }

        return $scheme;
    }

    /**
     * @param   int|null $port
     * @param   string $scheme
     *
     * @return  int|null
     */
    protected function filterPort($port, $scheme = null)
    {
        if (null === $port) {
            return $port;
        }

        $port = (int) $port;

        if ($scheme) {
            $schemes = $this->getvalidSchemes();

            if (isset($schemes[$scheme]) && $port === $schemes[$scheme]) {
                return null;
            }
        }

        if (1 > $port || 65535 < $port) {
            throw new InvalidArgumentException("Invalid port: {$port}");
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
        $path = ltrim($path, '/');

        $path = '/' . $path;

        return $this->encode($path);
    }

    /**
     * @param   string|null $query
     *
     * @return  string
     */
    protected function parseQuery($query)
    {
        $query = ltrim($query, '?');

        $fields = explode('&', $query);

        foreach ($fields as $key => $data) {
            $fields[$key] = $this->parseQueryPair($data);
        }

        return implode('&', $fields);
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
            return $this->encode($data[0]);
        }
        return $this->encode($data[0]) . '=' . $this->encode($data[1]);
    }

    /**
     * @param   string $fragment
     *
     * @return  string
     */
    protected function parseFragment($fragment)
    {
        $fragment = ltrim($fragment, '#');

        return $this->encode($fragment);
    }

    /**
     * @param   string $string
     *
     * @return  string
     */
    protected function encode($string)
    {
        return preg_replace_callback('/(?:[^A-Za-z0-9_~\-\.\/%]|%(?![A-Fa-f0-9]{2}))/', function (array $matches) {
            return rawurlencode($matches[0]);
        }, $string);
    }
}
