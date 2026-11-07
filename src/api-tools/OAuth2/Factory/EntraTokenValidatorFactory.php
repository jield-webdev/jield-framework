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

        /** @mago-var mixed $config */
        $config = $container->get('config')['api-tools-oauth2']['entra'] ?? [];
        /** @mago-var mixed $roles */
        $roles = $config['application_roles'] ?? [];
        $roles = array_values(Assert::isArray($roles));
        Assert::allString($roles);

        return ($config['diagnostics'] ?? false) === true
            ? new DevelopmentEntraTokenValidator($keys, $roles)
            : new EntraTokenValidator($keys, $roles);
    }
}
