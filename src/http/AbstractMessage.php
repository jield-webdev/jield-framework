<?php

namespace Jield\Http;

use Jield\Stdlib\Message;

use function in_array;
use function is_string;

/**
 * HTTP standard message (Request/Response)
 *
 * @link      http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4
 */
abstract class AbstractMessage extends Message
{
    /**#@+
     *
     * @const string Version constant numbers
     */
    public const string VERSION_10 = '1.0';
    public const string VERSION_11 = '1.1';
    public const string VERSION_2  = '2';
    /**#@-*/

    /** @var string */
    protected string $version = self::VERSION_11;

    /** @var Headers|null */
    protected ?Headers $headers;

    /**
     * Set the HTTP version for this object, one of 1.0, 1.1 or 2
     * (AbstractMessage::VERSION_10, AbstractMessage::VERSION_11, AbstractMessage::VERSION_2)
     *
     * @param string $version (Must be 1.0, 1.1 or 2)
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setVersion(string $version): static
    {
        if (! in_array(needle: $version, haystack: [self::VERSION_10, self::VERSION_11, self::VERSION_2])) {
            throw new Exception\InvalidArgumentException(
                message: 'Not valid or not supported HTTP version: ' . $version
            );
        }
        $this->version = $version;
        return $this;
    }

    /**
     * Return the HTTP version for this request
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Provide an alternate Parameter Container implementation for headers in this object,
     * (this is NOT the primary API for value setting, for that see getHeaders())
     *
     * @see    getHeaders()
     *
     * @return $this
     */
    public function setHeaders(Headers $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Return the header container responsible for headers
     *
     * @return Headers
     */
    public function getHeaders(): ?Headers
    {
        if ($this->headers === null || is_string(value: $this->headers)) {
            // this is only here for fromString lazy loading
            $this->headers = is_string(value: $this->headers) ? Headers::fromString(string: $this->headers) : new Headers();
        }

        return $this->headers;
    }

    /**
     * Allow PHP casting of this object
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
