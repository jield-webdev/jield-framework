<?php

namespace Jield\Http\Header;

use Laminas\Uri\Http as HttpUri;
use Laminas\Uri\UriInterface;

/**
 * Content-Location Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.36
 */
class Referer extends AbstractLocation
{
    /**
     * Set the URI/URL for this header
     * according to RFC Referer URI should not have fragment
     *
     * @param string|UriInterface $uri
     * @return $this
     */
    public function setUri(UriInterface|string $uri): static
    {
        parent::setUri(uri: $uri);
        $this->uri->setFragment(fragment: null);

        return $this;
    }

    /**
     * Return header name
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return 'Referer';
    }
}
