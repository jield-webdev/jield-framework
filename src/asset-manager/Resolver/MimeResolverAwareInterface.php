<?php

declare(strict_types=1);

namespace Jield\AssetManager\Resolver;

use Jield\AssetManager\Service\MimeResolver;

interface MimeResolverAwareInterface
{
    /**
     * Set the MimeResolver.
     */
    public function setMimeResolver(MimeResolver $mimeResolver);

    /**
     * Get the MimeResolver
     *
     * @return MimeResolver
     */
    public function getMimeResolver(): MimeResolver;
}
