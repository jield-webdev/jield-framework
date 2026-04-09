<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

/**
 * Factory responsible of building a set of {@see \Jield\Authorize\Guard\GuardInterface}
 */
class GuardsServiceFactory extends BaseProvidersServiceFactory
{
    public const string PROVIDER_SETTING = 'guards';
}
