<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

/**
 * Factory responsible of building a set of {@see \Jield\Authorize\Guard\GuardInterface}
 */
class GuardsServiceFactory extends BaseProvidersServiceFactory
{
    public const string PROVIDER_SETTING = 'guards';
}
