<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use Jield\ApiTools\OAuth2\DevelopmentEntraTokenValidator;
use Jield\ApiTools\OAuth2\EntraJwksProvider;
use Jield\ApiTools\OAuth2\EntraTokenValidator;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

final class EntraTokenValidatorFactory
{
    public function __invoke(ContainerInterface $container): EntraTokenValidator
    {
        $keys = Service::get($container, EntraJwksProvider::class);

        /** @var mixed $config */
        $config = $container->get('config')['api-tools-oauth2']['entra'] ?? [];
        /** @var mixed $roles */
        $roles = $config['application_roles'] ?? [];

        return ($config['diagnostics'] ?? false) === true
            ? new DevelopmentEntraTokenValidator($keys, Value::stringList($roles))
            : new EntraTokenValidator($keys, Value::stringList($roles));
    }
}
