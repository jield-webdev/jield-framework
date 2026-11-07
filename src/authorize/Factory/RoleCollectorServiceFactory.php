<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Collector\RoleCollector;
use Jield\Authorize\Provider\Identity\ProviderInterface;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory responsible of instantiating {@see \Jield\Authorize\Collector\RoleCollector}
 */
class RoleCollectorServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RoleCollector
    {
        $identityProvider = Service::get($container, ProviderInterface::class);

        return new RoleCollector(identityProvider: $identityProvider);
    }
}
