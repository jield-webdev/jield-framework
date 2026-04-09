<?php

namespace Jield\Http\Header;

use function is_numeric;
use function strtolower;

/**
 * Retry-After HTTP Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.37
 */
class RetryAfter extends AbstractDate
{
    /**
     * Value of header in delta-seconds
     * By default set to 1 hour
     *
     * @var int
     */
    protected int $deltaSeconds = 3600;

    /**
     * Create Retry-After header from string
     *
     * @param string $headerLine
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public static function fromString(string $headerLine): static
    {
        $dateHeader = new self();

        [$name, $date] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== strtolower(string: $dateHeader->getFieldName())) {
            throw new Exception\InvalidArgumentException(
                message: 'Invalid header line for "' . $dateHeader->getFieldName() . '" header string'
            );
        }

        if (is_numeric(value: $date)) {
            $dateHeader->setDeltaSeconds(delta: $date);
        } else {
            $dateHeader->setDate(date: $date);
        }

        return $dateHeader;
    }

    /**
     * Set number of seconds
     *
     * @param int $delta
     * @return $this
     */
    public function setDeltaSeconds(int $delta): static
    {
        $this->deltaSeconds = $delta;
        return $this;
    }

    /**
     * Get number of seconds
     *
     * @return int
     */
    public function getDeltaSeconds(): int
    {
        return $this->deltaSeconds;
    }

    /**
     * Get header name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'Retry-After';
    }

    /**
     * Returns date if it's set, or number of seconds
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        return $this->date === null ? $this->deltaSeconds : $this->getDate();
    }

    /**
     * Return header line
     *
     * @return string
     */
    public function toString(): string
    {
        return 'Retry-After: ' . $this->getFieldValue();
    }
}
