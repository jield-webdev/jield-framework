<?php

namespace Jield\Http\Header;

use function sprintf;
use function strtolower;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.11 \@todo find section
 *
 * @throws Exception\InvalidArgumentException
 */
class ContentTransferEncoding implements HeaderInterface
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
        if (strtolower(string: $name) !== 'content-transfer-encoding') {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Invalid header line for Content-Transfer-Encoding string: "%s"',
                $name
            ));
        }

        // @todo implementation details
        return new self(value: strtolower(string: $value));
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
        return 'Content-Transfer-Encoding';
    }

    /** @return string */
    public function getFieldValue(): string
    {
        return $this->value;
    }

    /** @return string */
    public function toString(): string
    {
        return 'Content-Transfer-Encoding: ' . $this->getFieldValue();
    }
}
