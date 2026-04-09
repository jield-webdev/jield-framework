<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Jield\Authorize\Config;

class IdentityProviderServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        return $container->get($container->get(Config::class)['identity_provider']);
    }
}
