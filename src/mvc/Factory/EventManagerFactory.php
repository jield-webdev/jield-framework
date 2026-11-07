<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Stdlib\Service;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class EventManagerFactory implements FactoryInterface
{
    /**
     * Create an EventManager instance
     *
     * Creates a new EventManager instance, seeding it with a shared instance
     * of SharedEventManager.
     *
     * @param string $requestedName
     * @param null|array $options
     * @return EventManager
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): EventManager
    {
        $shared = $container->has('SharedEventManager')
            ? Service::get($container, SharedEventManagerInterface::class, 'SharedEventManager')
            : null;

        return new EventManager($shared);
    }
}
