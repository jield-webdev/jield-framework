<?php

namespace Jield\Http\Header;

use Jield\Http\Header\Accept\FieldValuePart;
use Jield\Http\Header\Accept\FieldValuePart\EncodingFieldValuePart;

/**
 * Accept Encoding Header
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.3
 */
class AcceptEncoding extends AbstractAccept
{
    /** @var string */
    protected string $regexAddType = '#^([a-zA-Z0-9+-]+|\*)$#';

    /**
     * Get field name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'Accept-Encoding';
    }

    /**
     * Cast to string
     *
     * @return string
     */
    public function toString(): string
    {
        return 'Accept-Encoding: ' . $this->getFieldValue();
    }

    /**
     * Add an encoding, with the given priority
     *
     * @param string $type
     * @param float|int $priority
     * @return $this
     */
    public function addEncoding(string $type, float|int $priority = 1): static
    {
        return $this->addType(type: $type, priority: $priority);
    }

    /**
     * Does the header have the requested encoding?
     *
     * @param string $type
     * @return bool
     */
    public function hasEncoding(string $type): bool
    {
        return $this->hasType(matchAgainst: $type);
    }

    /**
     * Parse the keys contained in the header line
     *
     * @param string $fieldValuePart
     * @return EncodingFieldValuePart
     *@see \Jield\Http\Header\AbstractAccept::parseFieldValuePart()
     *
     */
    protected function parseFieldValuePart(string $fieldValuePart): EncodingFieldValuePart
    {
        $internalValues = parent::parseFieldValuePart(fieldValuePart: $fieldValuePart);

        return new FieldValuePart\EncodingFieldValuePart(internalValues: $internalValues);
    }
}
