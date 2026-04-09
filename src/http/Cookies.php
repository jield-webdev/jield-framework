<?php

namespace Jield\Http;

use ArrayIterator;
use Jield\Http\Header\SetCookie;
use Jield\Http\Headers;
use Laminas\Uri;

use function array_keys;
use function array_merge;
use function count;
use function is_array;
use function is_string;
use function sprintf;
use function strrpos;
use function substr;

/**
 * A Jield\Http\Cookies object is designed to contain and maintain HTTP cookies, and should
 * be used along with Jield\Http\Client in order to manage cookies across HTTP requests and
 * responses.
 *
 * The class contains an array of Jield\Http\Header\Cookie objects. Cookies can be added
 * automatically from a request or manually. Then, the Cookies class can find and return the
 * cookies needed for a specific HTTP request.
 *
 * A special parameter can be passed to all methods of this class that return cookies: Cookies
 * can be returned either in their native form (as Jield\Http\Header\Cookie objects) or as strings -
 * the later is suitable for sending as the value of the "Cookie" header in an HTTP request.
 * You can also choose, when returning more than one cookie, whether to get an array of strings
 * (by passing Jield\Http\Client\Cookies::COOKIE_STRING_ARRAY) or one unified string for all cookies
 * (by passing Jield\Http\Client\Cookies::COOKIE_STRING_CONCAT).
 *
 * @link       http://wp.netscape.com/newsref/std/cookie_spec.html for some specs.
 */
class Cookies extends Headers
{
    /**
     * Return cookie(s) as a Jield\Http\Cookie object
     */
    public const int COOKIE_OBJECT = 0;

    /**
     * Return cookie(s) as a string (suitable for sending in an HTTP request)
     */
    public const int COOKIE_STRING_ARRAY = 1;

    /**
     * Return all cookies as one long string (suitable for sending in an HTTP request)
     */
    public const int COOKIE_STRING_CONCAT = 2;

    /**
     * Return all cookies as one long string (strict mode)
     *  - Single space after the semi-colon separating each cookie
     *  - Remove trailing semi-colon, if any
     */
    public const int COOKIE_STRING_CONCAT_STRICT = 3;

    /** @var array */
    protected array $cookies = [];

    /** @var Headers */
    protected array $headers;

    /** @var array */
    protected array $rawCookies;

    /**
     * @static
     * @param string $string
     * @return \Jield\Http\Headers
     *@throws Exception\RuntimeException
     */
    public static function fromString(string $string): \Jield\Http\Headers
    {
        throw new Exception\RuntimeException(
            message: self::class . '::' . __FUNCTION__ . ' should not be used as a factory, use '
            . __NAMESPACE__ . '\Headers::fromString() instead.'
        );
    }

    /**
     * Add a cookie to the class. Cookie should be passed either as a Jield\Http\Header\SetCookie object
     * or as a string - in which case an object is created from the string.
     *
     * @param string|SetCookie $cookie
     * @param Uri\Uri|string|null $refUri Optional reference URI (for domain, path, secure)
     * @throws Exception\InvalidArgumentException
     */
    public function addCookie(SetCookie|string $cookie, string|Uri\Uri|null $refUri = null): void
    {
        if (is_string(value: $cookie)) {
            $cookie = SetCookie::fromString(headerLine: $cookie, bypassHeaderFieldName: $refUri);
        }

        if ($cookie instanceof SetCookie) {
            $domain = $cookie->getDomain();
            $path   = $cookie->getPath();
            if (! isset($this->cookies[$domain])) {
                $this->cookies[$domain] = [];
            }
            if (! isset($this->cookies[$domain][$path])) {
                $this->cookies[$domain][$path] = [];
            }
            $this->cookies[$domain][$path][$cookie->getName()] = $cookie;
            $this->rawCookies[]                                = $cookie;
        } else {
            throw new Exception\InvalidArgumentException(message: 'Supplient argument is not a valid cookie string or object');
        }
    }

    /**
     * Parse an HTTP response, adding all the cookies set in that response
     *
     * @param Uri\Uri|string $refUri Requested URI
     */
    public function addCookiesFromResponse(Response $response, string|Uri\Uri $refUri): void
    {
        $cookieHdrs = $response->getHeaders()->get(name: 'Set-Cookie');

        if (is_array(value: $cookieHdrs) || $cookieHdrs instanceof ArrayIterator) {
            foreach ($cookieHdrs as $cookie) {
                $this->addCookie(cookie: $cookie, refUri: $refUri);
            }
        } elseif (is_string(value: $cookieHdrs)) {
            $this->addCookie(cookie: $cookieHdrs, refUri: $refUri);
        }
    }

    /**
     * Get all cookies in the cookie jar as an array
     *
     * @param int $retAs Whether to return cookies as objects of \Jield\Http\Header\SetCookie or as strings
     * @return array|string
     */
    public function getAllCookies(int $retAs = self::COOKIE_OBJECT): array|string
    {
        return $this->_flattenCookiesArray(ptr: $this->cookies, retAs: $retAs);
    }

    /**
     * Return an array of all cookies matching a specific request according to the request URI,
     * whether session cookies should be sent or not, and the time to consider as "now" when
     * checking cookie expiry time.
     *
     * @param string|Uri\Uri $uri URI to check against (secure, domain, path)
     * @param bool $matchSessionCookies Whether to send session cookies
     * @param int $retAs Whether to return cookies as objects of \Jield\Http\Header\Cookie or as strings
     * @param int|null $now Override the current time when checking for expiry time
     * @return array|string
     *@throws Exception\InvalidArgumentException If invalid URI specified.
     */
    public function getMatchingCookies(
        string|Uri\Uri $uri,
        bool           $matchSessionCookies = true,
        int            $retAs = self::COOKIE_OBJECT,
        ?int           $now = null
    ): array|string
    {
        if (is_string(value: $uri)) {
            $uri = Uri\UriFactory::factory(uriString: $uri, defaultScheme: 'http');
        } elseif (! $uri instanceof Uri\Uri) {
            throw new Exception\InvalidArgumentException(message: 'Invalid URI string or object passed');
        }

        $host = $uri->getHost();
        if (empty($host)) {
            throw new Exception\InvalidArgumentException(message: 'Invalid URI specified; does not contain a host');
        }

        // First, reduce the array of cookies to only those matching domain and path
        $cookies = $this->_matchDomain(domain: $host);
        $cookies = $this->_matchPath(domains: $cookies, path: $uri->getPath());
        $cookies = $this->_flattenCookiesArray(ptr: $cookies, retAs: self::COOKIE_OBJECT);

        // Next, run Cookie->match on all cookies to check secure, time and session matching
        $ret = [];
        foreach ($cookies as $cookie) {
            if ($cookie->match($uri, $matchSessionCookies, $now)) {
                $ret[] = $cookie;
            }
        }
        // Now, use self::_flattenCookiesArray again - only to convert to the return format ;)
        $ret = $this->_flattenCookiesArray(ptr: $ret, retAs: $retAs);

        return $ret;
    }

    /**
     * Get a specific cookie according to a URI and name
     *
     * @param Uri\Uri|string $uri The uri (domain and path) to match
     * @param string $cookieName The cookie's name
     * @param int $retAs Whether to return cookies as objects of \Jield\Http\Header\SetCookie or as strings
     * @return SetCookie|string
     *@throws Exception\InvalidArgumentException If invalid URI specified or invalid $retAs value.
     */
    public function getCookie(string|Uri\Uri $uri, string $cookieName, int $retAs = self::COOKIE_OBJECT): SetCookie|false|string
    {
        if (is_string(value: $uri)) {
            $uri = Uri\UriFactory::factory(uriString: $uri, defaultScheme: 'http');
        } elseif (! $uri instanceof Uri\Uri) {
            throw new Exception\InvalidArgumentException(message: 'Invalid URI specified');
        }

        $host = $uri->getHost();
        if (empty($host)) {
            throw new Exception\InvalidArgumentException(message: 'Invalid URI specified; host missing');
        }

        // Get correct cookie path
        $path         = $uri->getPath() ?? '';
        $lastSlashPos = strrpos(haystack: $path, needle: '/') ?: 0;
        $path         = substr(string: $path, offset: 0, length: $lastSlashPos);
        if (! $path) {
            $path = '/';
        }

        if (isset($this->cookies[$uri->getHost()][$path][$cookieName])) {
            $cookie = $this->cookies[$uri->getHost()][$path][$cookieName];

            switch ($retAs) {
                case self::COOKIE_OBJECT:
                    return $cookie;

                case self::COOKIE_STRING_ARRAY:
                case self::COOKIE_STRING_CONCAT:
                    return $cookie->__toString();

                default:
                    throw new Exception\InvalidArgumentException(message: sprintf(
                        'Invalid value passed for $retAs: %s',
                        $retAs
                    ));
            }
        }

        return false;
    }

    /**
     * Helper function to recursively flatten an array. Should be used when exporting the
     * cookies array (or parts of it)
     *
     * @param array|SetCookie $ptr
     * @param int $retAs What value to return
     * @return array|string
     */
    // @codingStandardsIgnoreStart
    protected function _flattenCookiesArray(SetCookie|array $ptr, int $retAs = self::COOKIE_OBJECT): array|string
    {
        // @codingStandardsIgnoreEnd
        if (is_array(value: $ptr)) {
            $ret = $retAs === self::COOKIE_STRING_CONCAT ? '' : [];
            foreach ($ptr as $item) {
                if ($retAs === self::COOKIE_STRING_CONCAT) {
                    $ret .= $this->_flattenCookiesArray(ptr: $item, retAs: $retAs);
                } else {
                    $ret = array_merge($ret, $this->_flattenCookiesArray(ptr: $item, retAs: $retAs));
                }
            }
            return $ret;
        } elseif ($ptr instanceof SetCookie) {
            switch ($retAs) {
                case self::COOKIE_STRING_ARRAY:
                    return [$ptr->__toString()];

                case self::COOKIE_STRING_CONCAT:
                    return $ptr->__toString();

                case self::COOKIE_OBJECT:
                default:
                    return [$ptr];
            }
        }
    }

    /**
     * Return a subset of the cookies array matching a specific domain
     *
     * @param string $domain
     * @return array
     */
    // @codingStandardsIgnoreStart
    protected function _matchDomain(string $domain): array
    {
        // @codingStandardsIgnoreEnd
        $ret = [];

        foreach (array_keys(array: $this->cookies) as $cdom) {
            if (SetCookie::matchCookieDomain(cookieDomain: $cdom, host: $domain)) {
                $ret[$cdom] = $this->cookies[$cdom];
            }
        }

        return $ret;
    }

    /**
     * Return a subset of a domain-matching cookies that also match a specified path
     *
     * @param array $domains
     * @param string $path
     * @return array
     */
    // @codingStandardsIgnoreStart
    protected function _matchPath(array $domains, string $path): array
    {
        // @codingStandardsIgnoreEnd
        $ret = [];

        foreach ($domains as $dom => $pathsArray) {
            foreach (array_keys(array: $pathsArray) as $cpath) {
                if (SetCookie::matchCookiePath(cookiePath: $cpath, path: $path)) {
                    if (! isset($ret[$dom])) {
                        $ret[$dom] = [];
                    }

                    $ret[$dom][$cpath] = $pathsArray[$cpath];
                }
            }
        }

        return $ret;
    }

    /**
     * Create a new Cookies object and automatically load into it all the
     * cookies set in a Response object. If $uri is set, it will be
     * considered as the requested URI for setting default domain and path
     * of the cookie.
     *
     * @param Response $response HTTP Response object
     * @param Uri\Uri|string $refUri The requested URI
     * @return static
     * @todo Add the $uri functionality.
     */
    public static function fromResponse(Response $response, string|Uri\Uri $refUri): static
    {
        $jar = new self();
        $jar->addCookiesFromResponse(response: $response, refUri: $refUri);
        return $jar;
    }

    /**
     * Tells if the array of cookies is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count(value: $this) === 0;
    }

    /**
     * Empties the cookieJar of any cookie
     *
     * @return $this
     */
    public function reset(): static
    {
        $this->cookies = $this->rawCookies = [];
        return $this;
    }
}
