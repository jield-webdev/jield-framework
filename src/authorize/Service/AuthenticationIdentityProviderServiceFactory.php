<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Authorize\Provider\Identity\AuthenticationIdentityProvider;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Simple authentication provider factory
 */
class AuthenticationIdentityProviderServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): object|AuthenticationIdentityProvider
    {
        $user                   = $container->get('lmcuser_user_service');
        $simpleIdentityProvider = new AuthenticationIdentityProvider(authService: $user->getAuthService());
        $config                 = $container->get('Jield\Authorize\Config');

        $simpleIdentityProvider->setDefaultRole(defaultRole: $config['default_role']);
        $simpleIdentityProvider->setAuthenticatedRole(authenticatedRole: $config['authenticated_role']);

        return $simpleIdentityProvider;
    }
}
