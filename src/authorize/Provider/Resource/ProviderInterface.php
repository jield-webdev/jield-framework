<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Resource;

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
