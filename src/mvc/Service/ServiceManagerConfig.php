<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Jield\ModuleManager\Listener\ServiceListener;
use Jield\ModuleManager\ModuleManager;
use Jield\Mvc\Factory\EventManagerFactory;
use Jield\Mvc\Factory\ModuleManagerFactory;
use Jield\Mvc\Factory\ServiceListenerFactory;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\SharedEventManager;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Psr\Container\ContainerInterface;

class ServiceManagerConfig
{
    /**
     * Default service configuration.
     *
     * In addition to these, the constructor registers several factories and
     * initializers; see that method for details.
     *
     * @var array
     */
    protected array $config
        = [
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
     * Constructor
     *
     * Merges internal arrays with those passed via configuration, and also
     * defines:
     *
     * - factory for the service 'SharedEventManager'.
     * - initializer for EventManagerAwareInterface implementations
     */
    public function __construct(array $config = [])
    {
        $this->config['factories']['ServiceManager'] = static fn(
            ContainerInterface $container,
            string             $requestedName,
            ?array             $options = null
        ): ContainerInterface => $container;

        $this->config['factories']['SharedEventManager'] = static fn(
            ContainerInterface $container,
            string             $requestedName,
            ?array             $options = null
        ): SharedEventManager => new SharedEventManager();

        $this->config['initializers'] = ArrayUtils::merge($this->config['initializers'], [
            'EventManagerAwareInitializer' => static function (
                ContainerInterface $container,
                object|array       $instance
            ): void {
                if (!$instance instanceof EventManagerAwareInterface) {
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

        if ($config !== []) {
            $this->config = ArrayUtils::merge($this->config, $config);
        }
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
        $services->setAllowOverride(true);
        $services->configure($this->config);
        $services->setAllowOverride(false);

        return $services;
    }

    /**
     * Return all service configuration
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->config;
    }
}
