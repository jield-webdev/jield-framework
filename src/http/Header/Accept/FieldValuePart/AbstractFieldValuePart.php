<?php

namespace Jield\Http\Header\Accept\FieldValuePart;

use stdClass;

/**
 * Field Value Part
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 */
abstract class AbstractFieldValuePart
{
    /**
     * Internal object used for value retrieval
     *
     * @var object
     */
    private object $internalValues;

    /**
     * A Field Value Part this Field Value Part matched against.
     *
     * @var AbstractFieldValuePart
     */
    protected AbstractFieldValuePart $matchedAgainst;

    /**
     * @param object $internalValues
     */
    public function __construct(object $internalValues)
    {
        $this->internalValues = $internalValues;
    }

    /**
     * Set a Field Value Part this Field Value Part matched against.
     *
     * @return $this
     */
    public function setMatchedAgainst(AbstractFieldValuePart $matchedAgainst): static
    {
        $this->matchedAgainst = $matchedAgainst;
        return $this;
    }

    /**
     * Get a Field Value Part this Field Value Part matched against.
     *
     * @return AbstractFieldValuePart|null
     */
    public function getMatchedAgainst(): ?AbstractFieldValuePart
    {
        return $this->matchedAgainst;
    }

    /**
     * @return object
     */
    protected function getInternalValues(): object
    {
        return $this->internalValues;
    }

    /**
     * @return string $typeString
     */
    public function getTypeString(): string
    {
        return $this->getInternalValues()->typeString;
    }

    /**
     * @return float $priority
     */
    public function getPriority(): float
    {
        return (float) $this->getInternalValues()->priority;
    }

    /**
     * @return stdClass $params
     */
    public function getParams(): object|stdClass
    {
        return (object) $this->getInternalValues()->params;
    }

    /**
     * @return string $raw
     */
    public function getRaw(): string
    {
        return $this->getInternalValues()->raw;
    }

    /**
     * @param mixed $key
     * @return mixed
     */
    public function __get(mixed $key)
    {
        return $this->getInternalValues()->$key;
    }
}
