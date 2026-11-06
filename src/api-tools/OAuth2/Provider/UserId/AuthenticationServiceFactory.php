<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Provider\UserId;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Psr\Container\ContainerInterface;

class AuthenticationServiceFactory
{
    public function __invoke(ContainerInterface $container): AuthenticationService
    {
        /** @var mixed $config */
        $config = $container->get('config');

        if ($container->has(\Laminas\Authentication\AuthenticationService::class)) {
            return new AuthenticationService(
                authenticationService: \Jield\Stdlib\Service::get($container, \Laminas\Authentication\AuthenticationService::class),
                config: \Jield\Stdlib\Value::array($config),
            );
        }

        return new AuthenticationService(authenticationService: null, config: \Jield\Stdlib\Value::array($config));
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
