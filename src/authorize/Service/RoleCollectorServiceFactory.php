<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Authorize\Provider\Identity\ProviderInterface;
use Jield\Collector\RoleCollector;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory responsible of instantiating {@see \Jield\Collector\RoleCollector}
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
        /** @var ProviderInterface $identityProvider */
        $identityProvider = $container->get(ProviderInterface::class);

        return new RoleCollector(identityProvider: $identityProvider);
    }
}
