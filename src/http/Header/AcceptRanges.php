<?php

namespace Jield\Http\Header;

use function strtolower;

/**
 * Accept Ranges Header
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.5
 */
class AcceptRanges implements HeaderInterface
{
    /** @var null|string */
    protected ?string $rangeUnit;

    /**
     * @param string $headerLine
     * @return static
     */
    public static function fromString(string $headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== 'accept-ranges') {
            throw new Exception\InvalidArgumentException(
                message: 'Invalid header line for Accept-Ranges string'
            );
        }

        return new self(rangeUnit: $value);
    }

    /** @param string|null $rangeUnit */
    public function __construct(?string $rangeUnit = null)
    {
        if ($rangeUnit !== null) {
            $this->setRangeUnit(rangeUnit: $rangeUnit);
        }
    }

    /** @return string */
    public function getFieldName(): string
    {
        return 'Accept-Ranges';
    }

    /** @return string */
    public function getFieldValue(): string
    {
        return $this->getRangeUnit();
    }

    /**
     * @param string $rangeUnit
     * @return static
     */
    public function setRangeUnit(string $rangeUnit): static
    {
        HeaderValue::assertValid(value: $rangeUnit);
        $this->rangeUnit = $rangeUnit;
        return $this;
    }

    /** @return string */
    public function getRangeUnit(): string
    {
        return (string) $this->rangeUnit;
    }

    /** @return string */
    public function toString(): string
    {
        return 'Accept-Ranges: ' . $this->getFieldValue();
    }
}
