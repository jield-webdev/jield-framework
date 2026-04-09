<?php

namespace Jield\Http\Header;

use function array_pad;
use function array_walk;
use function explode;
use function implode;
use function in_array;
use function sprintf;
use function str_replace;
use function strcasecmp;
use function trim;

/**
 * Content Security Policy Level 3 Header
 *
 * @link http://www.w3.org/TR/CSP/
 */
class ContentSecurityPolicy implements MultipleHeaderInterface
{
    /**
     * Valid directive names
     *
     * @var array
     */
    protected array $validDirectiveNames = [
        // As per http://www.w3.org/TR/CSP/#directives
        // Fetch directives
        'child-src',
        'connect-src',
        'default-src',
        'font-src',
        'frame-src',
        'img-src',
        'manifest-src',
        'media-src',
        'object-src',
        'prefetch-src',
        'script-src',
        'script-src-elem',
        'script-src-attr',
        'style-src',
        'style-src-elem',
        'style-src-attr',
        'worker-src',

        // Document directives
        'base-uri',
        'plugin-types',
        'sandbox',

        // Navigation directives
        'form-action',
        'frame-ancestors',
        'navigate-to',

        // Reporting directives
        'report-uri',
        'report-to',

        // Other directives
        'block-all-mixed-content',
        'require-sri-for',
        'require-trusted-types-for',
        'trusted-types',
        'upgrade-insecure-requests',
    ];

    /**
     * The directives defined for this policy
     *
     * @var array
     */
    protected array $directives = [];

    /**
     * Get the list of defined directives
     *
     * @return array
     */
    public function getDirectives(): array
    {
        return $this->directives;
    }

    /**
     * Sets the directive to consist of the source list
     *
     * Reverses http://www.w3.org/TR/CSP/#parsing-1
     *
     * @param string $name The directive name.
     * @param array $sources The source list.
     * @return $this
     * @throws Exception\InvalidArgumentException If the name is not a valid directive name.
     */
    public function setDirective(string $name, array $sources): static
    {
        if (! in_array(needle: $name, haystack: $this->validDirectiveNames, strict: true)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects a valid directive name; received "%s"',
                __METHOD__,
                $name
            ));
        }

        if (
            $name === 'block-all-mixed-content'
            || $name === 'upgrade-insecure-requests'
        ) {
            if ($sources) {
                throw new Exception\InvalidArgumentException(message: sprintf(
                    'Received value for %s directive; none expected',
                    $name
                ));
            }

            $this->directives[$name] = '';
            return $this;
        }

        if (empty($sources)) {
            if ('report-uri' === $name) {
                if (isset($this->directives[$name])) {
                    unset($this->directives[$name]);
                }
                return $this;
            }

            $this->directives[$name] = "'none'";
            return $this;
        }

        array_walk(array: $sources, callback: [__NAMESPACE__ . '\HeaderValue', 'assertValid']);
        $this->directives[$name] = implode(separator: ' ', array: $sources);

        return $this;
    }

    /**
     * Create Content Security Policy header from a given header line
     *
     * @param string $headerLine The header line to parse.
     * @return static
     * @throws Exception\InvalidArgumentException If the name field in the given header line does not match.
     */
    public static function fromString(string $headerLine): static
    {
        $header         = new self();
        $headerName     = $header->getFieldName();
        [$name, $value] = GenericHeader::splitHeaderLine(headerLine: $headerLine);
        // Ensure the proper header name
        if (strcasecmp(string1: $name, string2: $headerName) !== 0) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Invalid header line for %s string: "%s"',
                $headerName,
                $name
            ));
        }
        // As per http://www.w3.org/TR/CSP/#parsing
        $tokens = explode(separator: ';', string: $value);
        foreach ($tokens as $token) {
            $token = trim(string: $token);
            if ($token) {
                [$directiveName, $directiveValue] = array_pad(array: explode(separator: ' ', string: $token, limit: 2), length: 2, value: null);
                if (! isset($header->directives[$directiveName])) {
                    $header->setDirective(
                        name: $directiveName,
                        sources: $directiveValue === null ? [] : [$directiveValue]
                    );
                }
            }
        }
        return $header;
    }

    /**
     * Get the header name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'Content-Security-Policy';
    }

    /**
     * Get the header value
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        $directives = [];
        foreach ($this->directives as $name => $value) {
            $directives[] = sprintf('%s %s;', $name, $value);
        }
        return str_replace(search: ' ;', replace: ';', subject: implode(separator: ' ', array: $directives));
    }

    /**
     * Return the header as a string
     *
     * @return string
     */
    public function toString(): string
    {
        return sprintf('%s: %s', $this->getFieldName(), $this->getFieldValue());
    }

    /** @return string */
    public function toStringMultipleHeaders(array $headers): string
    {
        $strings = [$this->toString()];
        foreach ($headers as $header) {
            if (! $header instanceof ContentSecurityPolicy) {
                throw new Exception\RuntimeException(
                    message: 'The ContentSecurityPolicy multiple header implementation can only'
                    . ' accept an array of ContentSecurityPolicy headers'
                );
            }
            $strings[] = $header->toString();
        }

        return implode(separator: "\r\n", array: $strings) . "\r\n";
    }
}
