<?php
namespace Icicle\Http\Message;

interface Response extends Message
{
    const CONT = 100;
    const SWITCHING_PROTOCOLS = 101;
    const PROCESSING = 102;

    const OK = 200;
    const CREATED = 201;
    const ACCEPTED = 202;
    const NON_AUTHORITATIVE = 203;
    const NO_CONTENT = 204;
    const RESET_CONTENT = 205;
    const PARTIAL_CONTENT = 206;
    const MULTI_STATUS = 207;
    const ALREADY_REPORTED = 208;
    const IM_USED = 226;

    const MULTIPLE_CHOICES = 300;
    const MOVED_PERMANENTLY = 301;
    const FOUND = 302;
    const SEE_OTHER = 303;
    const NOT_MODIFIED = 304;
    const USE_PROXY = 305;
    const SWITCH_PROXY = 306;
    const TEMPORARY_REDIRECT = 307;
    const PERMANENT_REDIRECT = 308;

    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const PAYMENT_REQUIRED = 402;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const NOT_ACCEPTABLE = 406;
    const PROXY_AUTHENTICATION_REQUIRED = 407;
    const REQUEST_TIMEOUT = 408;
    const CONFLICT = 409;
    const GONE = 410;
    const LENGTH_REQUIRED = 411;
    const PRECONDITION_FAILED = 412;
    const REQUEST_ENTITY_TOO_LARGE = 413;
    const REQUEST_URI_TOO_LARGE = 414;
    const UNSUPPORTED_MEDIA_TYPE = 415;
    const RANGE_NOT_SATISFIABLE = 416;
    const EXPECTATION_FAILED = 417;
    const TEAPOT = 418;
    const UNPROCESSABLE_ENTITY = 422;
    const LOCKED = 423;
    const FAILED_DEPENDENCIES = 424;
    const UNORDERED_COLLECTION = 425;
    const UPGRADE_REQUIRED = 426;
    const PRECONDITION_REQUIRED = 428;
    const TOO_MANY_REQUESTS = 429;
    const REQUEST_HEADER_TOO_LARGE = 431;

    const INTERNAL_SERVER_ERROR = 500;
    const NOT_IMPLEMENTED = 501;
    const BAD_GATEWAY = 502;
    const SERVICE_UNAVAILABLE = 503;
    const GATEWAY_TIMEOUT = 504;
    const HTTP_VERSION_NOT_SUPPORTED = 505;
    const VARIANT_ALSO_NEGOTIATES = 506;
    const INSUFFICIENT_STORAGE = 507;
    const LOOP_DETECTED = 508;
    const NOT_EXTENDED = 510;
    const NETWORK_AUTHENTICATION_REQUIRED = 511;
    
    /**
     * Returns the response status code.
     *
     * @return int
     */
    public function getStatusCode();

    /**
     * Returns the reason phrase describing the status code.
     *
     * @return string
     */
    public function getReasonPhrase();

    /**
     * @return \Icicle\Http\Message\Cookie\MetaCookie[]
     */
    public function getCookies();

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasCookie($name);

    /**
     * @param string $name
     *
     * @return \Icicle\Http\Message\Cookie\MetaCookie|null
     */
    public function getCookie($name);

    /**
     * Returns a new instance with the given status.
     *
     * @param int $code 3-digit status code.
     * @param string $reason Description of status code or null to use default reason associated with the status
     *     code given.
     *
     * @return self
     *
     * @throws \Icicle\Http\Exception\InvalidStatusException
     */
    public function withStatus($code, $reason = '');

    /**
     * @param string $name
     * @param string $value
     * @param int $expires
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     *
     * @return self
     */
    public function withCookie(
        $name,
        $value = '',
        $expires = 0,
        $path = '',
        $domain = '',
        $secure = false,
        $httpOnly = false
    );

    /**
     * @param string $name
     *
     * @return self
     */
    public function withoutCookie($name);
}
