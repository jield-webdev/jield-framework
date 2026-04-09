<?php

namespace Jield\Http\Header\Accept\FieldValuePart;

/**
 * Field Value Part
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 */
class AcceptFieldValuePart extends AbstractFieldValuePart
{
    /**
     * @return string
     */
    public function getSubtype(): string
    {
        return $this->getInternalValues()->subtype;
    }

    /**
     * @return string
     */
    public function getSubtypeRaw(): string
    {
        return $this->getInternalValues()->subtypeRaw;
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return $this->getInternalValues()->format;
    }
}
