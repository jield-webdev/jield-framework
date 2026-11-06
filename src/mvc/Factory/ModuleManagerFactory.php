<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\ModuleManager\Feature\ControllerPluginProviderInterface;
use Jield\ModuleManager\Feature\ControllerProviderInterface;
use Jield\ModuleManager\Feature\RouteProviderInterface;
use Jield\ModuleManager\Feature\ServiceProviderInterface;
use Jield\ModuleManager\Feature\ViewHelperProviderInterface;
use Jield\ModuleManager\Listener\DefaultListenerAggregate;
use Jield\ModuleManager\Listener\ListenerOptions;
use Jield\ModuleManager\ModuleEvent;
use Jield\ModuleManager\ModuleManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ModuleManagerFactory implements FactoryInterface
{
    /**
     * Creates and returns the module manager
     *
     * Instantiates the default module listeners, providing them configuration
     * from the "module_listener_options" key of the ApplicationConfig
     * service. Also sets the default config glob path.
     *
     * Module manager is instantiated and provided with an EventManager, to which
     * the default listener aggregate is attached. The ModuleEvent is also created
     * and attached to the module manager.
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return ModuleManager
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ModuleManager {
        /** @var mixed $configuration */
        $configuration = $container->get('ApplicationConfig');
        $listenerOptions = new ListenerOptions(\Jield\Stdlib\Value::stringKeyedArray(\Jield\Stdlib\Value::accessible($configuration)['module_listener_options']));
        $defaultListeners = new DefaultListenerAggregate($listenerOptions);
        $serviceListener = \Jield\Stdlib\Service::get($container, \Jield\ModuleManager\Listener\ServiceListener::class, 'ServiceListener');

        $serviceListener->addServiceManager(
            \Jield\Stdlib\Value::instance($container, \Laminas\ServiceManager\ServiceManager::class),
            'service_manager',
            ServiceProviderInterface::class,
            'getServiceConfig',
        );

        $serviceListener->addServiceManager(
            'ControllerManager',
            'controllers',
            ControllerProviderInterface::class,
            'getControllerConfig',
        );
        $serviceListener->addServiceManager(
            'ControllerPluginManager',
            'controller_plugins',
            ControllerPluginProviderInterface::class,
            'getControllerPluginConfig',
        );
        $serviceListener->addServiceManager(
            'ViewHelperManager',
            'view_helpers',
            ViewHelperProviderInterface::class,
            'getViewHelperConfig',
        );
        $serviceListener->addServiceManager(
            'RoutePluginManager',
            'route_manager',
            RouteProviderInterface::class,
            'getRouteConfig',
        );

        $events = \Jield\Stdlib\Service::get($container, \Laminas\EventManager\EventManagerInterface::class, 'EventManager');
        $defaultListeners->attach($events);
        $serviceListener->attach($events);

        $moduleEvent = new ModuleEvent();
        $moduleEvent->setParam('ServiceManager', $container);

        $moduleManager = new ModuleManager(\Jield\Stdlib\Value::accessible($configuration)['modules'], $events);
        $moduleManager->setEvent($moduleEvent);

        return $moduleManager;
    }
}
