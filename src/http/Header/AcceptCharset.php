<?php

namespace Jield\Http\Header;

use Jield\Http\Header\Accept\FieldValuePart;
use Jield\Http\Header\Accept\FieldValuePart\CharsetFieldValuePart;

/**
 * Accept Charset Header
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.2
 */
class AcceptCharset extends AbstractAccept
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
        return 'Accept-Charset';
    }

    /**
     * Cast to string
     *
     * @return string
     */
    public function toString(): string
    {
        return 'Accept-Charset: ' . $this->getFieldValue();
    }

    /**
     * Add a charset, with the given priority
     *
     * @param string $type
     * @param float|int $priority
     * @return $this
     */
    public function addCharset(string $type, float|int $priority = 1): static
    {
        return $this->addType(type: $type, priority: $priority);
    }

    /**
     * Does the header have the requested charset?
     *
     * @param string $type
     * @return bool
     */
    public function hasCharset(string $type): bool
    {
        return $this->hasType(matchAgainst: $type);
    }

    /**
     * Parse the keys contained in the header line
     *
     * @param string $fieldValuePart
     * @return CharsetFieldValuePart
     *@see \Jield\Http\Header\AbstractAccept::parseFieldValuePart()
     *
     */
    protected function parseFieldValuePart(string $fieldValuePart): CharsetFieldValuePart
    {
        $internalValues = parent::parseFieldValuePart(fieldValuePart: $fieldValuePart);

        return new FieldValuePart\CharsetFieldValuePart(internalValues: $internalValues);
    }
}
