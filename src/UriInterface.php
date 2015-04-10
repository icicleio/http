<?php
namespace Icicle\Http;

/**
 * Interface for URIs based on PSR-7 specification, adding methods for easier manipulation of query parameters and
 * getPort() always returns an integer as long as a scheme or port is set.
 */
interface UriInterface
{
    /**
     * Returns the scheme (without : or ://) or an empty string.
     *
     * @return  string
     */
    public function getScheme();

    /**
     * Returns the authority portion of the URI or an empty string if no host is set.
     *
     * @return  string String in [user[:password]@]host[:port] format.
     */
    public function getAuthority();

    /**
     * Returns the user and password portion of the URI, or an empty string.
     *
     * @return  string String in user[:password] format.
     */
    public function getUserInfo();

    /**
     * Returns the host or an empty string if no host is set.
     *
     * @return  string
     */
    public function getHost();

    /**
     * Returns the port or null if no port is set and no scheme is set.
     *
     * @return  int|null
     */
    public function getPort();

    /**
     * Returns the path portion of the URI.
     *
     * @return  string Path including proceeding /.
     */
    public function getPath();

    /**
     * Returns the query portion of the URI (does not include ? prefix). Key names are sorted through ksort().
     *
     * @return  string
     */
    public function getQuery();

    /**
     * Returns the value for the given query key name or null if the key name does not exist.
     *
     * @param   string $name
     *
     * @return  string|null
     */
    public function getQueryValue($name);

    /**
     * Returns the fragment portion of the URI (does not include # prefix).
     *
     * @return  string
     */
    public function getFragment();

    /**
     * Returns a new instance with the given scheme or no scheme if null. :// or : suffix should be trimmed.
     *
     * @param   string|null $scheme
     *
     * @return  self
     */
    public function withScheme($scheme);

    /**
     * Returns a new instance with the given user and password. Use null for $user to remove user info.
     *
     * @param   string|null $user
     * @param   string|null $password
     *
     * @return  self
     */
    public function withUserInfo($user, $password = null);

    /**
     * Returns a new instance with the given port or null to remove port.
     *
     * @param   int|null $port
     *
     * @return  self
     */
    public function withPort($port);

    /**
     * Returns a new instance with the given query string or null to remove query string. Any ? prefix should be
     * trimmed.
     *
     * @param   string $query
     *
     * @return  self
     */
    public function withQuery($query);

    /**
     * Returns a new instance with the given name and value pair in the query string (i.e., $name=$value)
     *
     * @param   string $name
     * @param   string $value
     *
     * @return  self
     */
    public function withQueryValue($name, $value);

    /**
     * Returns a new instance with the given key name removed from the query string.
     *
     * @param   string $name
     *
     * @return  self
     */
    public function withoutQueryValue($name);

    /**
     * Returns a new instance with the given fragment or null to remove fragment. Any # prefix should be trimmed.
     *
     * @param   string $fragment
     *
     * @return  self
     */
    public function withFragment($fragment);

    /**
     * Returns the URI string representation.
     *
     * @return  string
     */
    public function __toString();
}
