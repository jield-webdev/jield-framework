<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

/**
 * Factory responsible of a set of {@see \Jield\Authorize\Provider\Resource\ProviderInterface}
 */
class ResourceProvidersServiceFactory extends BaseProvidersServiceFactory
{
    public const string PROVIDER_SETTING = 'resource_providers';
}
