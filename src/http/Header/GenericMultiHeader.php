<?php

namespace Jield\Http\Header;

use function explode;
use function implode;
use function strpos;

class GenericMultiHeader extends GenericHeader implements MultipleHeaderInterface
{
    /**
     * @param string $headerLine
     * @return static|static[]
     */
    public static function fromString(string $headerLine): static
    {
        [$fieldName, $fieldValue] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        if (strpos(haystack: $fieldValue, needle: ',')) {
            $headers = [];
            foreach (explode(separator: ',', string: $fieldValue) as $multiValue) {
                $headers[] = new self(fieldName: $fieldName, fieldValue: $multiValue);
            }
            return $headers;
        } else {
            return new self(fieldName: $fieldName, fieldValue: $fieldValue);
        }
    }

    /** @return string */
    public function toStringMultipleHeaders(array $headers): string
    {
        $name   = $this->getFieldName();
        $values = [$this->getFieldValue()];
        foreach ($headers as $header) {
            if (!$header instanceof static) {
                throw new Exception\InvalidArgumentException(
                    message: 'This method toStringMultipleHeaders was expecting an array of headers of the same type'
                );
            }
            $values[] = $header->getFieldValue();
        }
        return $name . ': ' . implode(separator: ',', array: $values) . "\r\n";
    }
}
