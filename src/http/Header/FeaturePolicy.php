<?php

namespace Jield\Http\Header;

use function array_pad;
use function array_walk;
use function explode;
use function implode;
use function in_array;
use function sprintf;
use function strcasecmp;
use function trim;

/**
 * Feature Policy (based on Editor’s Draft, 28 November 2019)
 *
 * @link https://w3c.github.io/webappsec-feature-policy/
 */
class FeaturePolicy implements HeaderInterface
{
    /**
     * Valid directive names
     *
     * @see https://github.com/w3c/webappsec-feature-policy/blob/master/features.md
     *
     * @var string[]
     */
    protected array $validDirectiveNames = [
        // Standardized Features
        'accelerometer',
        'ambient-light-sensor',
        'autoplay',
        'battery',
        'camera',
        'display-capture',
        'document-domain',
        'fullscreen',
        'execution-while-not-rendered',
        'execution-while-out-of-viewport',
        'gyroscope',
        'magnetometer',
        'microphone',
        'midi',
        'payment',
        'picture-in-picture',
        'sync-xhr',
        'usb',
        'wake-lock',
        'xr',

        // Proposed Features
        'encrypted-media',
        'geolocation',
        'speaker',

        // Experimental Features
        'document-write',
        'font-display-late-swap',
        'layout-animations',
        'loading-frame-default-eager',
        'loading-image-default-eager',
        'legacy-image-formats',
        'oversized-images',
        'sync-script',
        'unoptimized-lossy-images',
        'unoptimized-lossless-images',
        'unsized-media',
        'vertical-scroll',
        'serial',
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
     * @param string $name The directive name.
     * @param string[] $sources The source list.
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
        if (empty($sources)) {
            $this->directives[$name] = "'none'";
            return $this;
        }

        array_walk(array: $sources, callback: [__NAMESPACE__ . '\HeaderValue', 'assertValid']);

        $this->directives[$name] = implode(separator: ' ', array: $sources);
        return $this;
    }

    /**
     * Create Feature Policy header from a given header line
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
        // As per https://w3c.github.io/webappsec-feature-policy/#algo-parse-policy-directive
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
        return 'Feature-Policy';
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
        return implode(separator: ' ', array: $directives);
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
}
