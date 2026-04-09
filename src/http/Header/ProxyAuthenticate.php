<?php

namespace Jield\Http\Header;

use function implode;
use function sprintf;
use function strtolower;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.33
 *
 * @throws Exception\InvalidArgumentException
 */
class ProxyAuthenticate implements MultipleHeaderInterface
{
    /** @var string */
    protected string $value;

    /**
     * @param string $headerLine
     * @return static
     */
    public static function fromString(string $headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== 'proxy-authenticate') {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Invalid header line for Proxy-Authenticate string: "%s"',
                $name
            ));
        }

        // @todo implementation details
        return new self(value: $value);
    }

    /** @param string|null $value */
    public function __construct(?string $value = null)
    {
        if ($value !== null) {
            HeaderValue::assertValid(value: $value);
            $this->value = $value;
        }
    }

    /** @return string */
    public function getFieldName(): string
    {
        return 'Proxy-Authenticate';
    }

    /** @return string */
    public function getFieldValue(): string
    {
        return $this->value;
    }

    /** @return string */
    public function toString(): string
    {
        return 'Proxy-Authenticate: ' . $this->getFieldValue();
    }

    /** @return string */
    public function toStringMultipleHeaders(array $headers): string
    {
        $strings = [$this->toString()];
        foreach ($headers as $header) {
            if (! $header instanceof ProxyAuthenticate) {
                throw new Exception\RuntimeException(
                    message: 'The ProxyAuthenticate multiple header implementation can only accept'
                    . ' an array of ProxyAuthenticate headers'
                );
            }
            $strings[] = $header->toString();
        }
        return implode(separator: "\r\n", array: $strings);
    }
}
