<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\Identity\Factory;

use Jield\Mvc\Plugin\Identity\Identity;
use Jield\Stdlib\Service;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class IdentityFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Identity
    {
        $plugin = new Identity();
        if ($container->has(AuthenticationService::class)) {
            $plugin->setAuthenticationService(Service::get($container, AuthenticationService::class));
            return $plugin;
        }
        if ($container->has(AuthenticationServiceInterface::class)) {
            $plugin->setAuthenticationService(Service::get($container, AuthenticationServiceInterface::class));
            return $plugin;
        }

        return $plugin;
    }
}
