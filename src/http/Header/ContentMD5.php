<?php

namespace Jield\Http\Header;

use function strtolower;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.15
 *
 * @throws Exception\InvalidArgumentException
 */
class ContentMD5 implements HeaderInterface
{
    /** @var string */
    protected string $value;

    /**
     * @param string $headerLine
     * @return static
     */
    public static function fromString(string $headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== 'content-md5') {
            throw new Exception\InvalidArgumentException(message: 'Invalid header line for Content-MD5 string: "' . $name . '"');
        }

        // @todo implementation details
        return new self(value: $value);
    }

    /** @param string|null $value */
    public function __construct(?string $value = null)
    {
        if ($value !== null) {
            HeaderValue::assertValid(value: $value);
            $this->value = $value;
        }
    }

    /** @return string */
    public function getFieldName(): string
    {
        return 'Content-MD5';
    }

    /** @return string */
    public function getFieldValue(): string
    {
        return $this->value;
    }

    /** @return string */
    public function toString(): string
    {
        return 'Content-MD5: ' . $this->getFieldValue();
    }
}
