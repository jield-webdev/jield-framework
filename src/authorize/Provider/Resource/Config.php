<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Resource;

/**
 * Array-based resources list
 */
class Config implements ProviderInterface
{
    /**
     * @param array<array-key, mixed> $resources Resource names, instances, or nested resource configuration.
     */
    public function __construct(
        protected array $resources = [],
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getResources(): array
    {
        return $this->resources;
    }
}
