<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Laminas\EventManager\EventManager;
use Laminas\ServiceManager\Factory\FactoryInterface;

class EventManagerFactory implements FactoryInterface
{
    /**
     * Create an EventManager instance
     *
     * Creates a new EventManager instance, seeding it with a shared instance
     * of SharedEventManager.
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return EventManager
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $shared = $container->has(id: 'SharedEventManager') ? $container->get('SharedEventManager') : null;

        return new EventManager(sharedEventManager: $shared);
    }
}
