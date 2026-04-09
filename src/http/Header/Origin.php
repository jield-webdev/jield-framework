<?php

namespace Jield\Http\Header;

use Laminas\Uri\UriFactory;

use function explode;
use function strtolower;

/**
 * @see http://tools.ietf.org/id/draft-abarth-origin-03.html#rfc.section.2
 *
 * @throws Exception\InvalidArgumentException
 */
class Origin implements HeaderInterface
{
    /** @var string */
    protected string $value = '';

    /**
     * @param string $headerLine
     * @return static
     */
    public static function fromString(string $headerLine): static
    {
        [$name, $value] = explode(separator: ': ', string: $headerLine, limit: 2);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== 'origin') {
            throw new Exception\InvalidArgumentException(message: 'Invalid header line for Origin string: "' . $name . '"');
        }

        $uri = UriFactory::factory(uriString: $value);
        if (! $uri->isValid()) {
            throw new Exception\InvalidArgumentException(message: 'Invalid header value for Origin key: "' . $name . '"');
        }

        return new self(value: $value);
    }

    /**
     * @param string|null $value
     */
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
        return 'Origin';
    }

    /** @return string */
    public function getFieldValue(): string
    {
        return $this->value;
    }

    /** @return string */
    public function toString(): string
    {
        return 'Origin: ' . $this->getFieldValue();
    }
}
