<?php

namespace Jield\Http\Header;

use Laminas\Uri\Exception as UriException;
use Laminas\Uri\UriFactory;
use Laminas\Uri\UriInterface;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

/**
 * Abstract Location Header
 * Supports headers that have URI as value
 *
 * @see Jield\Http\Header\Location
 * @see Jield\Http\Header\ContentLocation
 * @see Jield\Http\Header\Referer
 *
 * Note for 'Location' header:
 * While RFC 1945 requires an absolute URI, most of the browsers also support relative URI
 * This class allows relative URIs, and let user retrieve URI instance if strict validation needed
 */
abstract class AbstractLocation implements HeaderInterface
{
    /**
     * URI for this header
     *
     * @var UriInterface
     */
    protected UriInterface $uri;

    /**
     * Create location-based header from string
     *
     * @param string $headerLine
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public static function fromString(string $headerLine): static
    {
        $locationHeader = new static();

        // Jield-5520 - IIS bug, no space after colon
        [$name, $uri] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== strtolower(string: $locationHeader->getFieldName())) {
            throw new Exception\InvalidArgumentException(
                message: 'Invalid header line for "' . $locationHeader->getFieldName() . '" header string'
            );
        }

        HeaderValue::assertValid(value: $uri);
        $locationHeader->setUri(uri: trim(string: $uri));

        return $locationHeader;
    }

    /**
     * Set the URI/URL for this header, this can be a string or an instance of Laminas\Uri\Http
     *
     * @param string|UriInterface $uri
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setUri(UriInterface|string $uri): static
    {
        if (is_string(value: $uri)) {
            try {
                $uri = UriFactory::factory(uriString: $uri);
            } catch (UriException\InvalidUriPartException $e) {
                throw new Exception\InvalidArgumentException(
                    message: sprintf('Invalid URI passed as string (%s)', $uri),
                    code: $e->getCode(),
                    previous: $e
                );
            } catch (UriException\InvalidArgumentException $e) {
                throw new Exception\InvalidArgumentException(
                    message: sprintf('Invalid URI passed as string (%s)', $uri),
                    code: $e->getCode(),
                    previous: $e
                );
            }
        } elseif (!$uri instanceof UriInterface) {
            throw new Exception\InvalidArgumentException(message: 'URI must be an instance of Laminas\Uri\Http or a string');
        }
        $this->uri = $uri;

        return $this;
    }

    /**
     * Return the URI for this header
     *
     * @return string
     */
    public function getUri(): UriInterface|string
    {
        if ($this->uri instanceof UriInterface) {
            return $this->uri->toString();
        }
        return $this->uri;
    }

    /**
     * Return the URI for this header as an instance of Laminas\Uri\Http
     *
     * @return UriInterface
     */
    public function uri(): UriInterface
    {
        if ($this->uri === null || is_string(value: $this->uri)) {
            $this->uri = UriFactory::factory(uriString: $this->uri);
        }
        return $this->uri;
    }

    /**
     * Get header value as URI string
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        return $this->getUri();
    }

    /**
     * Output header line
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->getFieldName() . ': ' . $this->getUri();
    }

    /**
     * Allow casting to string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
