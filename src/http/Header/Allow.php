<?php

namespace Jield\Http\Header;

use Jield\Http\Request;

use function array_keys;
use function explode;
use function implode;
use function preg_match;
use function sprintf;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Allow Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.7
 */
class Allow implements HeaderInterface
{
    /**
     * List of request methods
     * true states that method is allowed, false - disallowed
     * By default GET and POST are allowed
     *
     * @var array
     */
    protected array $methods = [
        Request::METHOD_OPTIONS => false,
        Request::METHOD_GET     => true,
        Request::METHOD_HEAD    => false,
        Request::METHOD_POST    => true,
        Request::METHOD_PUT     => false,
        Request::METHOD_DELETE  => false,
        Request::METHOD_TRACE   => false,
        Request::METHOD_CONNECT => false,
        Request::METHOD_PATCH   => false,
    ];

    /**
     * Create Allow header from header line
     *
     * @param string $headerLine
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public static function fromString(string $headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== 'allow') {
            throw new Exception\InvalidArgumentException(message: 'Invalid header line for Allow string: "' . $name . '"');
        }

        $header = new self();
        $header->disallowMethods(disallowedMethods: array_keys(array: $header->getAllMethods()));
        $header->allowMethods(allowedMethods: explode(separator: ',', string: $value));

        return $header;
    }

    /**
     * Get header name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'Allow';
    }

    /**
     * Get comma-separated list of allowed methods
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        return implode(separator: ', ', array: array_keys(array: $this->methods, filter_value: true, strict: true));
    }

    /**
     * Get list of all defined methods
     *
     * @return array
     */
    public function getAllMethods(): array
    {
        return $this->methods;
    }

    /**
     * Get list of allowed methods
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return array_keys(array: $this->methods, filter_value: true, strict: true);
    }

    /**
     * Allow methods or list of methods
     *
     * @param array|string $allowedMethods
     * @return $this
     */
    public function allowMethods(array|string $allowedMethods): static
    {
        foreach ((array) $allowedMethods as $method) {
            $method = trim(string: strtoupper(string: $method));
            if (preg_match(pattern: '/\s/', subject: $method)) {
                throw new Exception\InvalidArgumentException(message: sprintf(
                    'Unable to whitelist method; "%s" is not a valid method',
                    $method
                ));
            }
            $this->methods[$method] = true;
        }

        return $this;
    }

    /**
     * Disallow methods or list of methods
     *
     * @param array|string $disallowedMethods
     * @return $this
     */
    public function disallowMethods(array|string $disallowedMethods): static
    {
        foreach ((array) $disallowedMethods as $method) {
            $method = trim(string: strtoupper(string: $method));
            if (preg_match(pattern: '/\s/', subject: $method)) {
                throw new Exception\InvalidArgumentException(message: sprintf(
                    'Unable to blacklist method; "%s" is not a valid method',
                    $method
                ));
            }
            $this->methods[$method] = false;
        }

        return $this;
    }

    /**
     * Convenience alias for @param array|string $disallowedMethods
     * @return $this
     *@see disallowMethods()
     *
     */
    public function denyMethods(array|string $disallowedMethods): static
    {
        return $this->disallowMethods(disallowedMethods: $disallowedMethods);
    }

    /**
     * Check whether method is allowed
     *
     * @param string $method
     * @return bool
     */
    public function isAllowedMethod(string $method): bool
    {
        $method = trim(string: strtoupper(string: $method));

        // disallow unknown method
        if (! isset($this->methods[$method])) {
            $this->methods[$method] = false;
        }

        return $this->methods[$method];
    }

    /**
     * Return header as string
     *
     * @return string
     */
    public function toString(): string
    {
        return 'Allow: ' . $this->getFieldValue();
    }
}
