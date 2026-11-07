<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Provider\UserId;

use Jield\Stdlib\Service;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Psr\Container\ContainerInterface;

class AuthenticationServiceFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AuthenticationService
    {
        /** @mago-var mixed $config */
        $config = $container->get('config');

        if ($container->has(\Laminas\Authentication\AuthenticationService::class)) {
            return new AuthenticationService(
                authenticationService: Service::get($container, \Laminas\Authentication\AuthenticationService::class),
                config: \Webmozart\Assert\Assert::isArray($config),
            );
        }

        return new AuthenticationService(
            authenticationService: null,
            config: \Webmozart\Assert\Assert::isArray($config),
        );
    }

    /**
     * Provided for backwards compatibility; proxies to __invoke().
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function createService(ServiceLocatorInterface $container): AuthenticationService
    {
        return $this(container: $container);
    }
}
