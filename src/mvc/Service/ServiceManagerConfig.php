<?php

declare(strict_types=1);

namespace Jield\Mvc\Service;

use Psr\Container\ContainerInterface;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\SharedEventManager;
use Laminas\EventManager\SharedEventManagerInterface;
use Jield\ModuleManager\Listener\ServiceListener;
use Jield\ModuleManager\ModuleManager;
use Laminas\ServiceManager\ServiceManager;
use Jield\Stdlib\ArrayUtils;

final class ServiceManagerConfig
{
    protected array $config = [
        'abstract_factories' => [],
        'aliases'            => [
            'EventManagerInterface'            => EventManager::class,
            EventManagerInterface::class       => 'EventManager',
            ModuleManager::class               => 'ModuleManager',
            ServiceListener::class             => 'ServiceListener',
            SharedEventManager::class          => 'SharedEventManager',
            'SharedEventManagerInterface'      => 'SharedEventManager',
            SharedEventManagerInterface::class => 'SharedEventManager',
        ],
        'delegators'         => [],
        'factories'          => [
            'EventManager'    => EventManagerFactory::class,
            'ModuleManager'   => ModuleManagerFactory::class,
            'ServiceListener' => ServiceListenerFactory::class,
        ],
        'lazy_services'      => [],
        'initializers'       => [],
        'invokables'         => [],
        'services'           => [],
        'shared'             => [
            'EventManager' => false,
        ],
    ];

    /**
     * Merges internal arrays with those passed via configuration, and also
     * defines:
     *
     * - factory for the service 'SharedEventManager'.
     * - initializer for EventManagerAwareInterface implementations
     */
    public function __construct(array $config = [])
    {
        $this->config['factories']['ServiceManager'] = static fn(
            ContainerInterface $container
        ): ServiceManager => $container;

        $this->config['factories']['SharedEventManager'] = static fn(): SharedEventManager => new SharedEventManager();

        $this->config['initializers'] = ArrayUtils::merge(a: $this->config['initializers'], b: [
            'EventManagerAwareInitializer' => static function (
                ContainerInterface $container,
                object $instance
            ): void {
                if (! $instance instanceof EventManagerAwareInterface) {
                    return;
                }
                $eventManager = $instance->getEventManager();
                // If the instance has an EM WITH an SEM composed, do nothing.
                if (
                    $eventManager instanceof EventManagerInterface
                    && $eventManager->getSharedManager() instanceof SharedEventManagerInterface
                ) {
                    return;
                }
                $instance->setEventManager($container->get('EventManager'));
            },
        ]);

        $this->config = ArrayUtils::merge(a: $this->config, b: $config);
    }

    /**
     * Configure service container.
     *
     * Uses the configuration present in the instance to configure the provided
     * service container.
     *
     * Before doing so, it adds a "service" entry for the ServiceManager class,
     * pointing to the provided service container.
     *
     * @return ServiceManager
     */
    public function configureServiceManager(ServiceManager $services): ServiceManager
    {
        $this->config['services'][ServiceManager::class] = $services;

        // This is invoked as part of the bootstrapping process, and requires
        // the ability to override services.
        $services->setAllowOverride(flag: true);
        $services->configure($this->config);
        $services->setAllowOverride(flag: false);

        return $services;
    }

    public function toArray(): array
    {
        return $this->config;
    }
}
