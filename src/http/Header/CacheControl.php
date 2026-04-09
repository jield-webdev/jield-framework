<?php

namespace Jield\Http\Header;

use function array_key_exists;
use function implode;
use function is_bool;
use function ksort;
use function preg_match;
use function rtrim;
use function sprintf;
use function strlen;
use function strtolower;
use function substr;
use function trim;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9
 *
 * @throws Exception\InvalidArgumentException
 */
class CacheControl implements HeaderInterface
{
    /** @var string */
    protected string $value;

    /**
     * Array of Cache-Control directives
     *
     * @var array
     */
    protected array $directives = [];

    /**
     * Creates a CacheControl object from a headerLine
     *
     * @param string $headerLine
     * @return static
     *@throws Exception\InvalidArgumentException
     */
    public static function fromString(string $headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== 'cache-control') {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Invalid header line for Cache-Control string: "%s"',
                $name
            ));
        }

        HeaderValue::assertValid(value: $value);
        $directives = static::parseValue(value: $value);

        $header = new self();
        foreach ($directives as $key => $value) {
            $header->addDirective(key: $key, value: $value);
        }

        return $header;
    }

    /**
     * Required from HeaderDescription interface
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'Cache-Control';
    }

    /**
     * Checks if the internal directives array is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->directives);
    }

    /**
     * Add a directive
     * For directives like 'max-age=60', $value = '60'
     * For directives like 'private', use the default $value = true
     *
     * @param string $key
     * @param bool|string $value
     * @return $this
     */
    public function addDirective(string $key, bool|string $value = true): static
    {
        HeaderValue::assertValid(value: $key);
        if (! is_bool(value: $value)) {
            HeaderValue::assertValid(value: $value);
        }
        $this->directives[$key] = $value;
        return $this;
    }

    /**
     * Check the internal directives array for a directive
     *
     * @param string $key
     * @return bool
     */
    public function hasDirective(string $key): bool
    {
        return array_key_exists(key: $key, array: $this->directives);
    }

    /**
     * Fetch the value of a directive from the internal directive array
     *
     * @param string $key
     * @return string|null
     */
    public function getDirective(string $key): ?string
    {
        return array_key_exists(key: $key, array: $this->directives) ? $this->directives[$key] : null;
    }

    /**
     * Remove a directive
     *
     * @param string $key
     * @return $this
     */
    public function removeDirective(string $key): static
    {
        unset($this->directives[$key]);
        return $this;
    }

    /**
     * Assembles the directives into a comma-delimited string
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        $parts = [];
        ksort(array: $this->directives);
        foreach ($this->directives as $key => $value) {
            if (true === $value) {
                $parts[] = $key;
            } else {
                if (preg_match(pattern: '#[^a-zA-Z0-9._-]#', subject: $value)) {
                    $value = '"' . $value . '"';
                }
                $parts[] = $key . '=' . $value;
            }
        }
        return implode(separator: ', ', array: $parts);
    }

    /**
     * Returns a string representation of the HTTP Cache-Control header
     *
     * @return string
     */
    public function toString(): string
    {
        return 'Cache-Control: ' . $this->getFieldValue();
    }

    /**
     * Internal function for parsing the value part of a
     * HTTP Cache-Control header
     *
     * @param string $value
     * @return array
     *@throws Exception\InvalidArgumentException
     */
    protected static function parseValue(string $value): array
    {
        $value = trim(string: $value);

        $directives = [];

        // handle empty string early so we don't need a separate start state
        if ($value === '') {
            return $directives;
        }

        $lastMatch = null;

        // phpcs:disable Generic.PHP.DiscourageGoto.Found

        state_directive:
        switch (static::match(tokens: ['[a-zA-Z][a-zA-Z_-]*'], string: $value, lastMatch: $lastMatch)) {
            case 0:
                $directive = $lastMatch;
                goto state_value;
                // intentional fall-through

            default:
                throw new Exception\InvalidArgumentException(message: 'expected DIRECTIVE');
        }

        state_value:
        switch (static::match(tokens: ['="[^"]*"', '=[^",\s;]*'], string: $value, lastMatch: $lastMatch)) {
            case 0:
                $directives[$directive] = substr(string: $lastMatch, offset: 2, length: -1);
                goto state_separator;
                // intentional fall-through

            case 1:
                $directives[$directive] = rtrim(string: substr(string: $lastMatch, offset: 1));
                goto state_separator;
                // intentional fall-through

            default:
                $directives[$directive] = true;
                goto state_separator;
        }

        state_separator:
        switch (static::match(tokens: ['\s*,\s*', '$'], string: $value, lastMatch: $lastMatch)) {
            case 0:
                goto state_directive;
                // intentional fall-through

            case 1:
                return $directives;

            default:
                throw new Exception\InvalidArgumentException(message: 'expected SEPARATOR or END');
        }

        // phpcs:enable
    }

    /**
     * Internal function used by parseValue to match tokens
     *
     * @param array $tokens
     * @param string $string
     * @param string $lastMatch
     * @return int
     */
    protected static function match(array $tokens, string &$string, string &$lastMatch): int
    {
        // Ensure we have a string
        $value = $string;

        foreach ($tokens as $i => $token) {
            if (preg_match(pattern: '/^' . $token . '/', subject: $value, matches: $matches)) {
                $lastMatch = $matches[0];
                $string    = substr(string: $value, offset: strlen(string: $matches[0]));
                return $i;
            }
        }
        return -1;
    }
}
