<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

/**
 * Factory responsible of a set of {@see \Jield\Authorize\Provider\Role\ProviderInterface}
 */
class RoleProvidersServiceFactory extends BaseProvidersServiceFactory
{
    public const string PROVIDER_SETTING = 'role_providers';
}
