<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use Jield\ApiTools\OAuth2\DevelopmentEntraTokenValidator;
use Jield\ApiTools\OAuth2\EntraJwksProvider;
use Jield\ApiTools\OAuth2\EntraTokenValidator;
use Jield\Stdlib\Service;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

final class EntraTokenValidatorFactory
{
    public function __invoke(ContainerInterface $container): EntraTokenValidator
    {
        $keys = Service::get($container, EntraJwksProvider::class);

        $config = $container->get('config')['api-tools-oauth2']['entra'] ?? [];
        $roles = $config['application_roles'] ?? [];
        $roles = array_values($roles);
        Assert::allString($roles);

        return ($config['diagnostics'] ?? false) === true
            ? new DevelopmentEntraTokenValidator($keys, $roles)
            : new EntraTokenValidator($keys, $roles);
    }
}
