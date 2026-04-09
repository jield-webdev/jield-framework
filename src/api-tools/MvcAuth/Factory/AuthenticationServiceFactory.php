<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\Authentication\AuthenticationService;
use Jield\Authentication\Storage\NonPersistent;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class AuthenticationServiceFactory implements FactoryInterface
{
    /**
     * Create and return an AuthenticationService instance.
     *
     * @param string $requestedName
     */
    #[Override]
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): AuthenticationService
    {
        return new AuthenticationService(storage: $container->get(NonPersistent::class));
    }
}
