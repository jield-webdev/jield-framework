<?php

namespace Jield\Http\Header;

use function is_int;
use function is_numeric;
use function strtolower;

use const PHP_INT_MAX;

/**
 * Age HTTP Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.6
 */
class Age implements HeaderInterface
{
    /**
     * Estimate of the amount of time in seconds since the response
     *
     * @var int
     */
    protected int $deltaSeconds;

    /**
     * Create Age header from string
     *
     * @param string $headerLine
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public static function fromString(string $headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== 'age') {
            throw new Exception\InvalidArgumentException(message: 'Invalid header line for Age string: "' . $name . '"');
        }

        return new self(deltaSeconds: $value);
    }

    /** @param int|null $deltaSeconds */
    public function __construct(?int $deltaSeconds = null)
    {
        if ($deltaSeconds !== null) {
            $this->setDeltaSeconds(delta: $deltaSeconds);
        }
    }

    /**
     * Get header name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'Age';
    }

    /**
     * Get header value (number of seconds)
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        return (string) $this->getDeltaSeconds();
    }

    /**
     * Set number of seconds
     *
     * @param int $delta
     * @return $this
     */
    public function setDeltaSeconds(int $delta): static
    {
        if (! is_int(value: $delta) && ! is_numeric(value: $delta)) {
            throw new Exception\InvalidArgumentException(message: 'Invalid delta provided');
        }
        $this->deltaSeconds = (int) $delta;
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
     * Return header line
     * In case of overflow RFC states to set value of 2147483648 (2^31)
     *
     * @return string
     */
    public function toString(): string
    {
        return 'Age: ' . ($this->deltaSeconds >= PHP_INT_MAX ? '2147483648' : $this->deltaSeconds);
    }
}
