<?php

namespace Jield\Http\Header;

use function count;
use function explode;
use function is_string;
use function ltrim;
use function preg_match;

/**
 * Content-Location Header
 */
class GenericHeader implements HeaderInterface
{
    /** @var string */
    protected string $fieldName;

    /** @var string */
    protected string $fieldValue;

    /**
     * Factory to generate a header object from a string
     *
     * @param string $headerLine
     * @return static
     */
    public static function fromString(string $headerLine): static
    {
        [$fieldName, $fieldValue] = self::splitHeaderLine(headerLine: $headerLine);

        return new self(fieldName: $fieldName, fieldValue: $fieldValue);
    }

    /**
     * Splits the header line in `name` and `value` parts.
     *
     * @param string $headerLine
     * @return string[] `name` in the first index and `value` in the second.
     * @throws Exception\InvalidArgumentException If header does not match with the format ``name:value``.
     */
    public static function splitHeaderLine(string $headerLine): array
    {
        $parts = explode(separator: ':', string: $headerLine, limit: 2);
        if (count(value: $parts) !== 2) {
            throw new Exception\InvalidArgumentException(message: 'Header must match with the format "name:value"');
        }

        if (! HeaderValue::isValid(value: $parts[1])) {
            throw new Exception\InvalidArgumentException(message: 'Invalid header value detected');
        }

        $parts[1] = ltrim(string: $parts[1]);

        return $parts;
    }

    /**
     * Constructor
     *
     * @param string|null $fieldName
     * @param string|null $fieldValue
     */
    public function __construct(?string $fieldName = null, ?string $fieldValue = null)
    {
        if ($fieldName) {
            $this->setFieldName(fieldName: $fieldName);
        }

        if ($fieldValue !== null) {
            $this->setFieldValue(fieldValue: $fieldValue);
        }
    }

    /**
     * Set header field name
     *
     * @param string $fieldName
     * @return $this
     * @throws Exception\InvalidArgumentException If the name does not match with RFC 2616 format.
     */
    public function setFieldName(string $fieldName): static
    {
        if (! is_string(value: $fieldName) || empty($fieldName)) {
            throw new Exception\InvalidArgumentException(message: 'Header name must be a string');
        }

        /*
         * Following RFC 7230 section 3.2
         *
         * header-field = field-name ":" [ field-value ]
         * field-name   = token
         * token        = 1*tchar
         * tchar        = "!" / "#" / "$" / "%" / "&" / "'" / "*" / "+" / "-" / "." /
         *                "^" / "_" / "`" / "|" / "~" / DIGIT / ALPHA
         */
        if (! preg_match(pattern: '/^[!#$%&\'*+\-\.\^_`|~0-9a-zA-Z]+$/', subject: $fieldName)) {
            throw new Exception\InvalidArgumentException(
                message: 'Header name must be a valid RFC 7230 (section 3.2) field-name.'
            );
        }

        $this->fieldName = $fieldName;
        return $this;
    }

    /**
     * Retrieve header field name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Set header field value
     *
     * @param string $fieldValue
     * @return $this
     */
    public function setFieldValue(string $fieldValue): static
    {
        $fieldValue = $fieldValue;
        HeaderValue::assertValid(value: $fieldValue);

        if (preg_match(pattern: '/^\s+$/', subject: $fieldValue)) {
            $fieldValue = '';
        }

        $this->fieldValue = $fieldValue;
        return $this;
    }

    /**
     * Retrieve header field value
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        return $this->fieldValue;
    }

    /**
     * Cast to string as a well formed HTTP header line
     *
     * Returns in form of "NAME: VALUE\r\n"
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->getFieldName() . ': ' . $this->getFieldValue();
    }
}
