<?php

namespace Jield\Http\Header;

use DateTime;
use DateTimeInterface;
use Laminas\Uri\Uri;
use Laminas\Uri\UriFactory;

use function array_key_exists;
use function array_pop;
use function count;
use function gettype;
use function gmdate;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function max;
use function preg_match;
use function preg_quote;
use function preg_split;
use function sprintf;
use function str_replace;
use function strpos;
use function strrpos;
use function strtolower;
use function strtotime;
use function time;
use function urldecode;
use function urlencode;

use const PHP_INT_MAX;
use const PHP_INT_SIZE;

/**
 * @see http://www.ietf.org/rfc/rfc2109.txt
 * @see http://www.w3.org/Protocols/rfc2109/rfc2109
 *
 * @throws Exception\InvalidArgumentException
 */
class SetCookie implements MultipleHeaderInterface
{
    /**
     * Cookie will not be sent for any cross-domain requests whatsoever.
     * Even if the user simply navigates to the target site with a regular link, the cookie will not be sent.
     */
    public const string SAME_SITE_STRICT = 'Strict';

    /**
     * Cookie will not be passed for any cross-domain requests unless it's a regular link that navigates user
     * to the target site.
     * Other requests methods (such as POST and PUT) and XHR requests will not contain this cookie.
     */
    public const string SAME_SITE_LAX = 'Lax';

    /**
     * Cookie will be sent with same-site and cross-site requests.
     */
    public const string SAME_SITE_NONE = 'None';

    /**
     * @internal
     */
    public const array SAME_SITE_ALLOWED_VALUES = [
        'strict' => self::SAME_SITE_STRICT,
        'lax'    => self::SAME_SITE_LAX,
        'none'   => self::SAME_SITE_NONE,
    ];

    /**
     * @deprecated This property is deprecated, and will be removed
     *
     * @var string
     */
    public string $type;

    /**
     * Cookie name
     *
     * @var string|null
     */
    protected ?string $name;

    /**
     * Cookie value
     *
     * @var string|null
     */
    protected ?string $value;

    /**
     * Version
     *
     * @var int|null
     */
    protected ?int $version;

    /**
     * Max Age
     *
     * @var int|null
     */
    protected ?int $maxAge;

    /**
     * Cookie expiry date
     *
     * @var int|null
     */
    protected ?int $expires;

    /**
     * Cookie domain
     *
     * @var string|null
     */
    protected ?string $domain;

    /**
     * Cookie path
     *
     * @var string|null
     */
    protected ?string $path;

    /**
     * Whether the cookie is secure or not
     *
     * @var bool|null
     */
    protected ?bool $secure;

    /**
     * If the value need to be quoted or not
     *
     * @var bool
     */
    protected bool $quoteFieldValue = false;

    /** @var bool|null */
    protected ?bool $httponly;

    /** @var string|null */
    protected ?string $sameSite;

    /** @var bool */
    protected bool $encodeValue = true;

    /**
     * @static
     * @param string $headerLine
     * @return HeaderInterface
     *@throws Exception\InvalidArgumentException
     */
    public static function fromString(string $headerLine): HeaderInterface
    {
        static $setCookieProcessor = null;

        if ($setCookieProcessor === null) {
            $setCookieClass     = static::class;
            $setCookieProcessor = function ($headerLine) use ($setCookieClass) {
                /** @var SetCookie $header */
                $header        = new $setCookieClass();
                $keyValuePairs = preg_split(pattern: '#;\s*#', subject: $headerLine);

                foreach ($keyValuePairs as $keyValue) {
                    if (preg_match(pattern: '#^(?P<headerKey>[^=]+)=\s*("?)(?P<headerValue>[^"]*)\2#', subject: $keyValue, matches: $matches)) {
                        $headerKey   = $matches['headerKey'];
                        $headerValue = $matches['headerValue'];
                    } else {
                        $headerKey   = $keyValue;
                        $headerValue = null;
                    }

                    // First K=V pair is always the cookie name and value
                    if ($header->getName() === null) {
                        $header->setName(name: $headerKey);
                        $header->setValue(value: urldecode(string: $headerValue ?? ''));

                        // set no encode value if raw and encoded values are the same
                        if (urldecode(string: $headerValue ?? '') === $headerValue) {
                            $header->setEncodeValue(encodeValue: false);
                        }
                        continue;
                    }

                    // Process the remaining elements
                    switch (str_replace(search: ['-', '_'], replace: '', subject: strtolower(string: $headerKey))) {
                        case 'expires':
                            $header->setExpires(expires: $headerValue);
                            break;
                        case 'domain':
                            $header->setDomain(domain: $headerValue);
                            break;
                        case 'path':
                            $header->setPath(path: $headerValue);
                            break;
                        case 'secure':
                            $header->setSecure(secure: true);
                            break;
                        case 'httponly':
                            $header->setHttponly(httponly: true);
                            break;
                        case 'version':
                            $header->setVersion(version: (int) $headerValue);
                            break;
                        case 'maxage':
                            $header->setMaxAge(maxAge: $headerValue);
                            break;
                        case 'samesite':
                            $header->setSameSite(sameSite: $headerValue);
                            break;
                        default:
                            // Intentionally omitted
                    }
                }

                return $header;
            };
        }

        [$name, $value] = GenericHeader::splitHeaderLine(headerLine: $headerLine);
        HeaderValue::assertValid(value: $value);

        // some sites return set-cookie::value, this is to get rid of the second :
        $name = strtolower(string: $name) === 'set-cookie:' ? 'set-cookie' : $name;

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== 'set-cookie') {
            throw new Exception\InvalidArgumentException(message: 'Invalid header line for Set-Cookie string: "' . $name . '"');
        }

        $multipleHeaders = preg_split(pattern: '#(?<!Sun|Mon|Tue|Wed|Thu|Fri|Sat),\s*#', subject: $value);

        if (count(value: $multipleHeaders) <= 1) {
            return $setCookieProcessor(headerLine: array_pop(array: $multipleHeaders));
        } else {
            $headers = [];
            foreach ($multipleHeaders as $headerLine) {
                $headers[] = $setCookieProcessor(headerLine: $headerLine);
            }
            return $headers;
        }
    }

    /**
     * Cookie object constructor
     *
     * @param string|null $name
     * @param string|null $value
     * @param DateTime|int|string|null $expires
     * @param string|null $path
     * @param string|null $domain
     * @param bool $secure
     * @param bool $httponly
     * @param int|null $maxAge
     * @param int|null $version
     * @param string|null              $sameSite
     *@todo Add validation of each one of the parameters (legal domain, etc.)
     */
    public function __construct(
        ?string                  $name = null,
        ?string                  $value = null,
        DateTime|int|string|null $expires = null,
        ?string                  $path = null,
        ?string                  $domain = null,
        bool                     $secure = false,
        bool                     $httponly = false,
        ?int                     $maxAge = null,
        ?int                     $version = null,
        ?string                  $sameSite = null
    ) {
        $this->type = 'Cookie';

        $this->setName(name: $name)
             ->setValue(value: $value)
             ->setVersion(version: $version)
             ->setMaxAge(maxAge: $maxAge)
             ->setDomain(domain: $domain)
             ->setExpires(expires: $expires)
             ->setPath(path: $path)
             ->setSecure(secure: $secure)
             ->setHttpOnly(httponly: $httponly)
             ->setSameSite(sameSite: $sameSite);
    }

    /**
     * @return bool
     */
    public function getEncodeValue(): bool
    {
        return $this->encodeValue;
    }

    /**
     * @param bool $encodeValue
     */
    public function setEncodeValue(bool $encodeValue): void
    {
        $this->encodeValue = $encodeValue;
    }

    /**
     * @return string 'Set-Cookie'
     */
    public function getFieldName(): string
    {
        return 'Set-Cookie';
    }

    /**
     * @throws Exception\RuntimeException
     * @return string
     */
    public function getFieldValue(): string
    {
        $name = $this->getName();
        if ($name === '' || $name === null) {
            return '';
        }

        $value = $this->encodeValue ? urlencode(string: $this->getValue() ?? '') : $this->getValue();
        if ($this->hasQuoteFieldValue()) {
            $value = '"' . $value . '"';
        }

        $fieldValue = $name . '=' . $value;

        $version = $this->getVersion();
        if ($version !== null) {
            $fieldValue .= '; Version=' . $version;
        }

        $maxAge = $this->getMaxAge();
        if ($maxAge !== null) {
            $fieldValue .= '; Max-Age=' . $maxAge;
        }

        $expires = $this->getExpires();
        if ($expires) {
            $fieldValue .= '; Expires=' . $expires;
        }

        $domain = $this->getDomain();
        if ($domain) {
            $fieldValue .= '; Domain=' . $domain;
        }

        $path = $this->getPath();
        if ($path) {
            $fieldValue .= '; Path=' . $path;
        }

        if ($this->isSecure()) {
            $fieldValue .= '; Secure';
        }

        if ($this->isHttponly()) {
            $fieldValue .= '; HttpOnly';
        }

        $sameSite = $this->getSameSite();
        if ($sameSite !== null && array_key_exists(key: strtolower(string: $sameSite), array: self::SAME_SITE_ALLOWED_VALUES)) {
            $fieldValue .= '; SameSite=' . $sameSite;
        }

        return $fieldValue;
    }

    /**
     * @param string|null $name
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setName(?string $name): static
    {
        HeaderValue::assertValid(value: $name);
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $value
     * @return $this
     */
    public function setValue(?string $value): static
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * @param int|null $version
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setVersion(?int $version): static
    {
        if ($version !== null && ! is_int(value: $version)) {
            throw new Exception\InvalidArgumentException(message: 'Invalid Version number specified');
        }
        $this->version = $version;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getVersion(): ?int
    {
        return $this->version;
    }

    /**
     * @param int $maxAge
     * @return $this
     */
    public function setMaxAge(int $maxAge): static
    {
        if ($maxAge === null || ! is_numeric(value: $maxAge)) {
            return $this;
        }

        $this->maxAge = max(0, (int) $maxAge);
        return $this;
    }

    /**
     * @return int|null
     */
    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }

    /**
     * @param DateTime|int|string|null $expires
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setExpires(DateTime|int|string|null $expires): static
    {
        if ($expires === null) {
            $this->expires = null;
            return $this;
        }

        if ($expires instanceof DateTime) {
            $expires = $expires->format(format: DateTimeInterface::COOKIE);
        }

        $tsExpires = $expires;

        if (is_string(value: $expires)) {
            $tsExpires = strtotime(datetime: $expires);

            // if $tsExpires is invalid and PHP is compiled as 32bit. Check if it fail reason is the 2038 bug
            if (! is_int(value: $tsExpires) && PHP_INT_SIZE === 4) {
                $dateTime = new DateTime(datetime: $expires);
                if ($dateTime->format(format: 'Y') > 2038) {
                    $tsExpires = PHP_INT_MAX;
                }
            }
        }

        if (! is_int(value: $tsExpires) || $tsExpires < 0) {
            throw new Exception\InvalidArgumentException(message: 'Invalid expires time specified');
        }

        $this->expires = $tsExpires;

        return $this;
    }

    /**
     * @param bool $inSeconds
     * @return int|string|null
     */
    public function getExpires(bool $inSeconds = false): int|string|null
    {
        if ($this->expires === null) {
            return null;
        }
        if ($inSeconds) {
            return $this->expires;
        }
        return gmdate(format: 'D, d-M-Y H:i:s', timestamp: $this->expires) . ' GMT';
    }

    /**
     * @param string|null $domain
     * @return $this
     */
    public function setDomain(?string $domain): static
    {
        HeaderValue::assertValid(value: $domain);
        $this->domain = $domain;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * @param string|null $path
     * @return $this
     */
    public function setPath(?string $path): static
    {
        HeaderValue::assertValid(value: $path);
        $this->path = $path;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param bool|null $secure
     * @return $this
     */
    public function setSecure(?bool $secure): static
    {
        if (null !== $secure) {
            $secure = $secure;
        }
        $this->secure = $secure;
        return $this;
    }

    /**
     * Set whether the value for this cookie should be quoted
     *
     * @param bool $quotedValue
     * @return $this
     */
    public function setQuoteFieldValue(bool $quotedValue): static
    {
        $this->quoteFieldValue = $quotedValue;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function isSecure(): ?bool
    {
        return $this->secure;
    }

    /**
     * @param bool|null $httponly
     * @return $this
     */
    public function setHttponly(?bool $httponly): static
    {
        if (null !== $httponly) {
            $httponly = $httponly;
        }
        $this->httponly = $httponly;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function isHttponly(): ?bool
    {
        return $this->httponly;
    }

    /**
     * Check whether the cookie has expired
     *
     * Always returns false if the cookie is a session cookie (has no expiry time)
     *
     * @param int|null $now Timestamp to consider as "now"
     * @return bool
     */
    public function isExpired(?int $now = null): bool
    {
        if ($now === null) {
            $now = time();
        }

        if (is_int(value: $this->expires) && $this->expires < $now) {
            return true;
        }

        return false;
    }

    /**
     * Check whether the cookie is a session cookie (has no expiry time set)
     *
     * @return bool
     */
    public function isSessionCookie(): bool
    {
        return $this->expires === null;
    }

    /**
     * @return string|null
     */
    public function getSameSite(): ?string
    {
        return $this->sameSite;
    }

    /**
     * @param string|null $sameSite
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setSameSite(?string $sameSite): static
    {
        if ($sameSite === null) {
            $this->sameSite = null;
            return $this;
        }
        if (! array_key_exists(key: strtolower(string: $sameSite), array: self::SAME_SITE_ALLOWED_VALUES)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Invalid value provided for SameSite directive: "%s"; expected one of: Strict, Lax or None',
                is_scalar(value: $sameSite) ? $sameSite : gettype(value: $sameSite)
            ));
        }
        $this->sameSite = self::SAME_SITE_ALLOWED_VALUES[strtolower(string: $sameSite)];
        return $this;
    }

    /**
     * Check whether the value for this cookie should be quoted
     *
     * @return bool
     */
    public function hasQuoteFieldValue(): bool
    {
        return $this->quoteFieldValue;
    }

    /**
     * @param string $requestDomain
     * @param string $path
     * @param bool $isSecure
     * @return bool
     */
    public function isValidForRequest(string $requestDomain, string $path, bool $isSecure = false): bool
    {
        if ($this->getDomain() && (strrpos(haystack: $requestDomain, needle: $this->getDomain()) === false)) {
            return false;
        }

        if ($this->getPath() && (strpos(haystack: $path, needle: $this->getPath()) !== 0)) {
            return false;
        }

        if ($this->secure && $this->isSecure() !== $isSecure) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether the cookie should be sent or not in a specific scenario
     *
     * @param string|Uri $uri URI to check against (secure, domain, path)
     * @param bool $matchSessionCookies Whether to send session cookies
     * @param int|null $now Override the current time when checking for expiry time
     * @return bool
     * @throws Exception\InvalidArgumentException If URI does not have HTTP or HTTPS scheme.
     */
    public function match(string|Uri $uri, bool $matchSessionCookies = true, ?int $now = null): bool
    {
        if (is_string(value: $uri)) {
            $uri = UriFactory::factory(uriString: $uri);
        }

        // Make sure we have a valid Jield_Uri_Http object
        if (! ($uri->isValid() && ($uri->getScheme() === 'http' || $uri->getScheme() === 'https'))) {
            throw new Exception\InvalidArgumentException(message: 'Passed URI is not a valid HTTP or HTTPS URI');
        }

        // Check that the cookie is secure (if required) and not expired
        if ($this->secure && $uri->getScheme() !== 'https') {
            return false;
        }
        if ($this->isExpired(now: $now)) {
            return false;
        }
        if ($this->isSessionCookie() && ! $matchSessionCookies) {
            return false;
        }

        // Check if the domain matches
        if (! self::matchCookieDomain(cookieDomain: $this->getDomain(), host: $uri->getHost())) {
            return false;
        }

        // Check that path matches using prefix match
        if (! self::matchCookiePath(cookiePath: $this->getPath(), path: $uri->getPath())) {
            return false;
        }

        // If we didn't die until now, return true.
        return true;
    }

    /**
     * Check if a cookie's domain matches a host name.
     *
     * Used by Jield\Http\Cookies for cookie matching
     *
     * @param string $cookieDomain
     * @param string $host
     * @return bool
     */
    public static function matchCookieDomain(string $cookieDomain, string $host): bool
    {
        $cookieDomain = strtolower(string: $cookieDomain);
        $host         = strtolower(string: $host);
        // Check for either exact match or suffix match
        return $cookieDomain === $host
            || preg_match(pattern: '/' . preg_quote(str: $cookieDomain) . '$/', subject: $host);
    }

    /**
     * Check if a cookie's path matches a URL path
     *
     * Used by Jield\Http\Cookies for cookie matching
     *
     * @param string $cookiePath
     * @param string $path
     * @return bool
     */
    public static function matchCookiePath(string $cookiePath, string $path): bool
    {
        return strpos(haystack: $path, needle: $cookiePath) === 0;
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return 'Set-Cookie: ' . $this->getFieldValue();
    }

    /**
     * @return string
     * @throws Exception\RuntimeException
     */
    public function toStringMultipleHeaders(array $headers): string
    {
        $headerLine = $this->toString();
        /** @var SetCookie $header */
        foreach ($headers as $header) {
            if (! $header instanceof SetCookie) {
                throw new Exception\RuntimeException(
                    message: 'The SetCookie multiple header implementation can only accept an array of SetCookie headers'
                );
            }
            $headerLine .= "\n" . $header->toString();
        }
        return $headerLine;
    }
}
