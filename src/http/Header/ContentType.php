<?php

namespace Jield\Http\Header;

use stdClass;

use function array_merge;
use function array_shift;
use function array_walk;
use function count;
use function explode;
use function gettype;
use function implode;
use function is_object;
use function is_string;
use function preg_match;
use function sprintf;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.17
 *
 * @throws Exception\InvalidArgumentException
 */
class ContentType implements HeaderInterface
{
    /** @var string */
    protected ?string $mediaType;

    /** @var array */
    protected array $parameters = [];

    /** @var string */
    protected string $value;

    /**
     * Factory method: create an object from a string representation
     *
     * @param string $headerLine
     * @return static
     */
    public static function fromString(string $headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== 'content-type') {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Invalid header line for Content-Type string: "%s"',
                $name
            ));
        }

        $parts     = explode(separator: ';', string: $value);
        $mediaType = array_shift(array: $parts);
        $header    = new self(value: $value, mediaType: trim(string: $mediaType));

        if (count(value: $parts) > 0) {
            $parameters = [];
            foreach ($parts as $parameter) {
                $parameter = trim(string: $parameter);
                if (! preg_match(pattern: '/^(?P<key>[^\s\=]+)\="?(?P<value>[^\s\"]*)"?$/', subject: $parameter, matches: $matches)) {
                    continue;
                }
                $parameters[$matches['key']] = $matches['value'];
            }
            $header->setParameters(parameters: $parameters);
        }

        return $header;
    }

    /**
     * @param string|null $value
     * @param string|null $mediaType
     */
    public function __construct(?string $value = null, ?string $mediaType = null)
    {
        if ($value !== null) {
            HeaderValue::assertValid(value: $value);
            $this->value = $value;
        }
        $this->mediaType = $mediaType;
    }

    /**
     * Determine if the mediatype value in this header matches the provided criteria
     *
     * @param array|string $matchAgainst
     * @return string|bool Matched value or false
     */
    public function match(array|string $matchAgainst): bool|string
    {
        if (is_string(value: $matchAgainst)) {
            $matchAgainst = $this->splitMediaTypesFromString(criteria: $matchAgainst);
        }

        $mediaType = $this->getMediaType();
        $left      = $this->getMediaTypeObjectFromString(string: $mediaType);

        foreach ($matchAgainst as $matchType) {
            $matchType = strtolower(string: $matchType);

            if ($mediaType === $matchType) {
                return $matchType;
            }

            $right = $this->getMediaTypeObjectFromString(string: $matchType);

            // Is the right side a wildcard type?
            if (($right->type === '*') && $this->validateSubtype(right: $right, left: $left)) {
                return $matchType;
            }

            // Do the types match?
            if (($right->type === $left->type) && $this->validateSubtype(right: $right, left: $left)) {
                return $matchType;
            }
        }

        return false;
    }

    /**
     * Create a string representation of the header
     *
     * @return string
     */
    public function toString(): string
    {
        return 'Content-Type: ' . $this->getFieldValue();
    }

    /**
     * Get the field name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'Content-Type';
    }

    /**
     * Get the field value
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        if (null !== $this->value) {
            return $this->value;
        }
        return $this->assembleValue();
    }

    /**
     * Set the media type
     *
     * @param string $mediaType
     * @return $this
     */
    public function setMediaType(string $mediaType): static
    {
        HeaderValue::assertValid(value: $mediaType);
        $this->mediaType = strtolower(string: $mediaType);
        $this->value     = null;
        return $this;
    }

    /**
     * Get the media type
     *
     * @return string
     */
    public function getMediaType(): string
    {
        return (string) $this->mediaType;
    }

    /**
     * Set additional content-type parameters
     *
     * @return $this
     */
    public function setParameters(array $parameters): static
    {
        foreach ($parameters as $key => $value) {
            HeaderValue::assertValid(value: $key);
            HeaderValue::assertValid(value: $value);
        }
        $this->parameters = array_merge($this->parameters, $parameters);
        $this->value      = null;
        return $this;
    }

    /**
     * Get any additional content-type parameters currently set
     *
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Set the content-type character set encoding
     *
     * @param string $charset
     * @return $this
     */
    public function setCharset(string $charset): static
    {
        HeaderValue::assertValid(value: $charset);
        $this->parameters['charset'] = $charset;
        $this->value                 = null;
        return $this;
    }

    /**
     * Get the content-type character set encoding, if any
     *
     * @return null|string
     */
    public function getCharset(): ?string
    {
        if (isset($this->parameters['charset'])) {
            return $this->parameters['charset'];
        }
        return null;
    }

    /**
     * Assemble the value based on the media type and any available parameters
     *
     * @return string
     */
    protected function assembleValue(): string
    {
        $mediaType = $this->getMediaType();
        if (empty($this->parameters)) {
            return $mediaType;
        }

        $parameters = [];
        foreach ($this->parameters as $key => $value) {
            $parameters[] = sprintf('%s=%s', $key, $value);
        }

        return sprintf('%s; %s', $mediaType, implode(separator: '; ', array: $parameters));
    }

    /**
     * Split comma-separated media types into an array
     *
     * @param string $criteria
     * @return array
     */
    protected function splitMediaTypesFromString(string $criteria): array
    {
        $mediaTypes = explode(separator: ',', string: $criteria);
        array_walk(
            array: $mediaTypes,
            callback: function (&$value): void {
                $value = trim(string: $value);
            }
        );

        return $mediaTypes;
    }

    /**
     * Split a mediatype string into an object with the following parts:
     *
     * - type
     * - subtype
     * - format
     *
     * @param string $string
     * @return stdClass
     */
    protected function getMediaTypeObjectFromString(string $string): object|stdClass
    {
        if (! is_string(value: $string)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Non-string mediatype "%s" provided',
                is_object(value: $string) ? $string::class : gettype(value: $string)
            ));
        }

        $parts = explode(separator: '/', string: $string, limit: 2);
        if (1 === count(value: $parts)) {
            throw new Exception\DomainException(message: sprintf(
                'Invalid mediatype "%s" provided',
                $string
            ));
        }

        $type    = array_shift(array: $parts);
        $subtype = array_shift(array: $parts);
        $format  = $subtype;
        if (false !== strpos(haystack: $subtype, needle: '+')) {
            $parts   = explode(separator: '+', string: $subtype, limit: 2);
            $subtype = array_shift(array: $parts);
            $format  = array_shift(array: $parts);
        }

        return (object) [
            'type'    => $type,
            'subtype' => $subtype,
            'format'  => $format,
        ];
    }

    /**
     * Validate a subtype
     *
     * @param stdClass $right
     * @param stdClass $left
     * @return bool
     */
    protected function validateSubtype(stdClass $right, stdClass $left): bool
    {
        // Is the right side a wildcard subtype?
        if ($right->subtype === '*') {
            return $this->validateFormat(right: $right, left: $left);
        }

        // Do the right side and left side subtypes match?
        if ($right->subtype === $left->subtype) {
            return $this->validateFormat(right: $right, left: $left);
        }

        // Is the right side a partial wildcard?
        if ('*' === substr(string: $right->subtype, offset: -1)) {
            // validate partial-wildcard subtype
            if (! $this->validatePartialWildcard(right: $right->subtype, left: $left->subtype)) {
                return false;
            }
            // Finally, verify format is valid
            return $this->validateFormat(right: $right, left: $left);
        }

        // Does the right side subtype match the left side format?
        if ($right->subtype === $left->format) {
            return true;
        }

        // At this point, there is no valid match
        return false;
    }

    /**
     * Validate the format
     *
     * Validate that the right side format matches what the left side defines.
     *
     * @param string $right
     * @param string $left
     * @return bool
     */
    protected function validateFormat(string $right, string $left): bool
    {
        if ($right->format && $left->format) {
            if ($right->format === '*') {
                return true;
            }
            if ($right->format === $left->format) {
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * Validate a partial wildcard (i.e., string ending in '*')
     *
     * @param string $right
     * @param string $left
     * @return bool
     */
    protected function validatePartialWildcard(string $right, string $left): bool
    {
        $requiredSegment = substr(string: $right, offset: 0, length: strlen(string: $right) - 1);
        if ($requiredSegment === $left) {
            return true;
        }

        if (strlen(string: $requiredSegment) >= strlen(string: $left)) {
            return false;
        }

        if (0 === strpos(haystack: $left, needle: $requiredSegment)) {
            return true;
        }

        return false;
    }
}
