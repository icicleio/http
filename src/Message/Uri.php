<?php
namespace Icicle\Http\Message;

/**
 * Interface for URIs based on PSR-7 specification, adding methods for easier manipulation of query parameters and
 * getPort() always returns an integer as long as a scheme or port is set.
 */
interface Uri
{
    /**
     * Returns the scheme (without : or ://) or an empty string.
     *
     * @return string
     */
    public function getScheme();

    /**
     * Returns the authority portion of the URI or an empty string if no host is set.
     *
     * @return string String in [user[:password]@]host[:port] format.
     */
    public function getAuthority();

    /**
     * Returns the user and password portion of the URI, or an empty string.
     *
     * @return string String in user[:password] format.
     */
    public function getUserInfo();

    /**
     * Returns the host or an empty string if no host is set.
     *
     * @return string
     */
    public function getHost();

    /**
     * Returns the port or 0 if no port is set and no scheme is set.
     *
     * @return int
     */
    public function getPort();

    /**
     * Returns the path portion of the URI.
     *
     * @return string Path including / prefix unless path is empty, then an empty string is returned.
     */
    public function getPath();

    /**
     * Returns the query portion of the URI (does not include ? prefix). Key names are sorted through ksort().
     *
     * @return string
     */
    public function getQuery();

    /**
     * Returns an array of the key/value pairs corresponding to the query portion of the URI.
     *
     * @return string[]
     */
    public function getQueryValues();

    /**
     * Determines if a query key exists for the given name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasQueryValue($name);

    /**
     * Returns the value for the given query key name or an empty if the key name does not exist.
     *
     * @param string $name
     *
     * @return string
     */
    public function getQueryValue($name);

    /**
     * Returns the fragment portion of the URI (does not include # prefix).
     *
     * @return string
     */
    public function getFragment();

    /**
     * Returns a new instance with the given scheme or no scheme if null. :// or : suffix should be trimmed.
     *
     * @param string $scheme
     *
     * @return self
     */
    public function withScheme($scheme = null);

    /**
     * Returns a new instance with the given user and password. Use null for $user to remove user info.
     *
     * @param string $user
     * @param string|null $password
     *
     * @return self
     */
    public function withUserInfo($user, $password = null);

    /**
     * Returns a new instance with the given host or null to remove the host.
     *
     * @param string|null $host
     *
     * @return self
     */
    public function withHost($host = null);

    /**
     * Returns a new instance with the given port or null to remove port.
     *
     * @param int|null $port
     *
     * @return self
     */
    public function withPort($port = null);

    /**
     * Returns a new instance with the given path or null to remove the path.
     *
     * @param string|null $path
     *
     * @return self
     */
    public function withPath($path = null);

    /**
     * Returns a new instance with the given query string or null to remove query string. Any ? prefix should be
     * trimmed.
     *
     * @param string|null $query
     *
     * @return self
     */
    public function withQuery($query = null);

    /**
     * Returns a new instance with the given name and value pair in the query string (i.e., $name=$value)
     *
     * @param string $name
     * @param string $value
     *
     * @return self
     */
    public function withQueryValue($name, $value);

    /**
     * Returns a new instance with the given key name removed from the query string.
     *
     * @param string $name
     *
     * @return self
     */
    public function withoutQueryValue($name);

    /**
     * Returns a new instance with the given fragment or null to remove fragment. Any # prefix should be trimmed.
     *
     * @param string|null $fragment
     *
     * @return self
     */
    public function withFragment($fragment = null);

    /**
     * Returns the URI string representation.
     *
     * @return string
     */
    public function __toString();
}
