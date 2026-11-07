<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Resource;

use Laminas\Permissions\Acl\Resource\ResourceInterface;

/**
 * Resource provider interface, provides existing resources list
 */
interface ProviderInterface
{
    /**
     * @return array<array-key, mixed> Resource names, instances, or nested resource configuration.
     */
    public function getResources(): array;
}
