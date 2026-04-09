<?php

namespace Jield\Http\Header;

use function str_replace;
use function strtolower;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.43
 *
 * @throws Exception\InvalidArgumentException
 */
class UserAgent implements HeaderInterface
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
        if (str_replace(search: ['_', ' ', '.'], replace: '-', subject: strtolower(string: $name)) !== 'user-agent') {
            throw new Exception\InvalidArgumentException(message: 'Invalid header line for User-Agent string: "' . $name . '"');
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
        return 'User-Agent';
    }

    /** @return string */
    public function getFieldValue(): string
    {
        return $this->value;
    }

    /** @return string */
    public function toString(): string
    {
        return 'User-Agent: ' . $this->getFieldValue();
    }
}
