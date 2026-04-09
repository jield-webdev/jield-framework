<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Provider\UserId;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Psr\Container\ContainerInterface;

class AuthenticationServiceFactory
{
    public function __invoke(ContainerInterface $container): AuthenticationService
    {
        $config = $container->get('config');

        if ($container->has(id: \Jield\Authentication\AuthenticationService::class)) {
            return new AuthenticationService(
                authenticationService: $container->get(\Jield\Authentication\AuthenticationService::class),
                config: $config
            );
        }

        return new AuthenticationService(authenticationService: null, config: $config);
    }

    /**
     * Provided for backwards compatibility; proxies to __invoke().
     *
     * @param ServiceLocatorInterface $container
     * @return AuthenticationService
     */
    public function createService(ServiceLocatorInterface $container): AuthenticationService
    {
        return $this(container: $container);
    }
}
