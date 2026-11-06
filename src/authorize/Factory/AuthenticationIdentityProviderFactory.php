<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Jield\Authorize\Provider\Identity\AuthenticationIdentityProvider;
use Jield\Authorize\Service\AccessRolesByUserInterface;
use Jield\Authorize\Service\HasPermitInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

final class AuthenticationIdentityProviderFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): AuthenticationIdentityProvider {
        /** @var mixed $config */
        $config = $container->get('config')['jield_authorize'] ?? [];

        if (!isset($config['access_service'])) {
            throw new \RuntimeException(message: sprintf(
                'Service access_service implementing interface %s is missing',
                AccessRolesByUserInterface::class,
            ));
        }

        if (!isset($config['permit_service'])) {
            throw new \RuntimeException(message: sprintf(
                'Service permit_service implementing interface %s is missing',
                HasPermitInterface::class,
            ));
        }

        return new AuthenticationIdentityProvider(
            authService: \Jield\Stdlib\Service::get($container, AuthenticationService::class),
            accessOrUserService: \Jield\Stdlib\Service::get($container, AccessRolesByUserInterface::class, \Jield\Stdlib\Value::string($config['access_service'])),
            permitService: \Jield\Stdlib\Service::get($container, HasPermitInterface::class, \Jield\Stdlib\Value::string($config['permit_service'])),
            authorizeConfig: \Jield\Stdlib\Value::array($container->get(Config::class)),
        );
    }
}
