<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

/**
 * Factory responsible of a set of {@see \Jield\Authorize\Provider\Rule\ProviderInterface}
 */
class RuleProvidersServiceFactory extends BaseProvidersServiceFactory
{
    public const string PROVIDER_SETTING = 'rule_providers';
}
