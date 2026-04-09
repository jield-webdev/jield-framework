<?php

namespace Jield\Http\Header\Accept\FieldValuePart;

/**
 * Field Value Part
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 */
class EncodingFieldValuePart extends AbstractFieldValuePart
{
    /**
     * @return string
     */
    public function getEncoding(): string
    {
        return $this->getInternalValues()->type;
    }
}
