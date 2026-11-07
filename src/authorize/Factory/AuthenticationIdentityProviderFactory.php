<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Jield\Authorize\Provider\Identity\AuthenticationIdentityProvider;
use Jield\Authorize\Service\AccessRolesByUserInterface;
use Jield\Authorize\Service\HasPermitInterface;
use Jield\Stdlib\Service;
use Laminas\Authentication\AuthenticationService;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Webmozart\Assert\Assert;

final class AuthenticationIdentityProviderFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): AuthenticationIdentityProvider {
        $config = $container->get('config')['jield_authorize'] ?? [];

        if (!isset($config['access_service'])) {
            throw new RuntimeException(message: sprintf(
                'Service access_service implementing interface %s is missing',
                AccessRolesByUserInterface::class,
            ));
        }

        if (!isset($config['permit_service'])) {
            throw new RuntimeException(message: sprintf(
                'Service permit_service implementing interface %s is missing',
                HasPermitInterface::class,
            ));
        }

        return new AuthenticationIdentityProvider(
            authService: Service::get($container, AuthenticationService::class),
            accessOrUserService: Service::get(
                $container,
                AccessRolesByUserInterface::class,
                Assert::string($config['access_service']),
            ),
            permitService: Service::get(
                $container,
                HasPermitInterface::class,
                Assert::string($config['permit_service']),
            ),
            authorizeConfig: $container->get(Config::class),
        );
    }
}
