<?php

namespace Jield\Http\Header;

use Jield\Http\Header\Accept\FieldValuePart;

use function strpos;
use function substr;
use function trim;

/**
 * Accept Header
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 */
class Accept extends AbstractAccept
{
    /** @var string */
    protected string $regexAddType = '#^([a-zA-Z+-]+|\*)/(\*|[a-zA-Z0-9+-]+)$#';

    /**
     * Get field name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'Accept';
    }

    /**
     * Cast to string
     *
     * @return string
     */
    public function toString(): string
    {
        return 'Accept: ' . $this->getFieldValue();
    }

    /**
     * Add a media type, with the given priority
     *
     * @param string $type
     * @param float|int $priority
     * @return $this
     */
    public function addMediaType(string $type, float|int $priority = 1, array $params = []): static
    {
        return $this->addType(type: $type, priority: $priority, params: $params);
    }

    /**
     * Does the header have the requested media type?
     *
     * @param string $type
     * @return bool
     */
    public function hasMediaType(string $type): bool
    {
        return $this->hasType(matchAgainst: $type);
    }

    /**
     * Parse the keys contained in the header line
     *
     * @param  string $fieldValuePart
     * @return FieldValuePart\AcceptFieldValuePart
     * @see    \Jield\Http\Header\AbstractAccept::parseFieldValuePart()
     *
     */
    protected function parseFieldValuePart(string $fieldValuePart): FieldValuePart\AcceptFieldValuePart
    {
        $raw = $fieldValuePart;
        if ($pos = strpos(haystack: $fieldValuePart, needle: '/')) {
            $type = trim(string: substr(string: $fieldValuePart, offset: 0, length: $pos));
        } else {
            $type = trim(string: $fieldValuePart);
        }

        $params = $this->getParametersFromFieldValuePart(fieldValuePart: $fieldValuePart);

        if ($pos = strpos(haystack: $fieldValuePart, needle: ';')) {
            $fieldValuePart = trim(string: substr(string: $fieldValuePart, offset: 0, length: $pos));
        }

        if (strpos(haystack: $fieldValuePart, needle: '/')) {
            $subtypeWhole = $format = $subtype = trim(string: substr(string: $fieldValuePart, offset: strpos(haystack: $fieldValuePart, needle: '/') + 1));
        } else {
            $subtypeWhole = '';
            $format       = '*';
            $subtype      = '*';
        }

        $pos = strpos(haystack: $subtype, needle: '+');
        if (false !== $pos) {
            $format  = trim(string: substr(string: $subtype, offset: $pos + 1));
            $subtype = trim(string: substr(string: $subtype, offset: 0, length: $pos));
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

        return new FieldValuePart\AcceptFieldValuePart(internalValues: (object) $aggregated);
    }
}
