<?php

namespace Jield\Http\Header;

use Jield\Http\Header\Accept\FieldValuePart;
use Jield\Http\Header\Accept\FieldValuePart\LanguageFieldValuePart;

use function strpos;
use function substr;
use function trim;

/**
 * Accept Language Header
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
 */
class AcceptLanguage extends AbstractAccept
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
        return 'Accept-Language';
    }

    /**
     * Cast to string
     *
     * @return string
     */
    public function toString(): string
    {
        return 'Accept-Language: ' . $this->getFieldValue();
    }

    /**
     * Add a language, with the given priority
     *
     * @param string $type
     * @param float|int $priority
     * @return $this
     */
    public function addLanguage(string $type, float|int $priority = 1): static
    {
        return $this->addType(type: $type, priority: $priority);
    }

    /**
     * Does the header have the requested language?
     *
     * @param string $type
     * @return bool
     */
    public function hasLanguage(string $type): bool
    {
        return $this->hasType(matchAgainst: $type);
    }

    /**
     * Parse the keys contained in the header line
     *
     * @param string $fieldValuePart
     * @return LanguageFieldValuePart
     *@see \Jield\Http\Header\AbstractAccept::parseFieldValuePart()
     *
     */
    protected function parseFieldValuePart(string $fieldValuePart): LanguageFieldValuePart
    {
        $raw = $fieldValuePart;
        if ($pos = strpos(haystack: $fieldValuePart, needle: '-')) {
            $type = trim(string: substr(string: $fieldValuePart, offset: 0, length: $pos));
        } else {
            $type = trim(string: substr(string: $fieldValuePart, offset: 0));
        }

        $params = $this->getParametersFromFieldValuePart(fieldValuePart: $fieldValuePart);

        if ($pos = strpos(haystack: $fieldValuePart, needle: ';')) {
            $fieldValuePart = $type = trim(string: substr(string: $fieldValuePart, offset: 0, length: $pos));
        }

        if (strpos(haystack: $fieldValuePart, needle: '-')) {
            $subtypeWhole = $format = $subtype = trim(string: substr(string: $fieldValuePart, offset: strpos(haystack: $fieldValuePart, needle: '-') + 1));
        } else {
            $subtypeWhole = '';
            $format       = '*';
            $subtype      = '*';
        }

        $aggregated = [
            'typeString' => trim(string: $fieldValuePart),
            'type'       => $type,
            'subtype'    => $subtype,
            'subtypeRaw' => $subtypeWhole,
            'format'     => $format,
            'priority'   => $params['q'] ?? 1,
            'params'     => $params,
            'raw'        => trim(string: $raw),
        ];

        return new FieldValuePart\LanguageFieldValuePart(internalValues: (object) $aggregated);
    }
}
