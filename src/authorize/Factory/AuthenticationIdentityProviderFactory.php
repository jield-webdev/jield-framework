<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authentication\AuthenticationService;
use Jield\Authorize\Provider\Identity\AuthenticationIdentityProvider;
use Jield\Authorize\Service\AccessRolesByUserInterface;
use Jield\Authorize\Service\HasPermitInterface;
use Jield\Authorize\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

final class AuthenticationIdentityProviderFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
                           $requestedName,
        ?array             $options = null
    ): AuthenticationIdentityProvider
    {
        $config = $container->get('config')['jield_authorize'] ?? [];

        if (!isset($config['access_service'])) {
            throw new \RuntimeException(
                message: sprintf(
                    'Service access_service implementing interface %s is missing',
                    AccessRolesByUserInterface::class
                )
            );
        }

        if (!isset($config['permit_service'])) {
            throw new \RuntimeException(
                message: sprintf(
                    'Service permit_service implementing interface %s is missing',
                    HasPermitInterface::class
                )
            );
        }

        //Check if the services have the correct interfaces
        Assert::implementsInterface(value: $config['access_service'], interface: AccessRolesByUserInterface::class);
        Assert::implementsInterface(value: $config['permit_service'], interface: HasPermitInterface::class);

        $dependencies = [
            $container->get(AuthenticationService::class),
            $container->get($config['access_service']),
            $container->get($config['permit_service']),
            $container->get(Config::class),
        ];

        return new AuthenticationIdentityProvider(... $dependencies);
    }
}
