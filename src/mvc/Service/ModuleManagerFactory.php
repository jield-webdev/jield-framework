<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
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
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $configuration    = $container->get('ApplicationConfig');
        $listenerOptions  = new ListenerOptions(options: $configuration['module_listener_options']);
        $defaultListeners = new DefaultListenerAggregate(options: $listenerOptions);
        $serviceListener  = $container->get('ServiceListener');

        $serviceListener->addServiceManager(
            $container,
            'service_manager',
            ServiceProviderInterface::class,
            'getServiceConfig'
        );

        $serviceListener->addServiceManager(
            'ControllerManager',
            'controllers',
            ControllerProviderInterface::class,
            'getControllerConfig'
        );
        $serviceListener->addServiceManager(
            'ControllerPluginManager',
            'controller_plugins',
            ControllerPluginProviderInterface::class,
            'getControllerPluginConfig'
        );
        $serviceListener->addServiceManager(
            'ViewHelperManager',
            'view_helpers',
            ViewHelperProviderInterface::class,
            'getViewHelperConfig'
        );
        $serviceListener->addServiceManager(
            'RoutePluginManager',
            'route_manager',
            RouteProviderInterface::class,
            'getRouteConfig'
        );

        $events = $container->get('EventManager');
        $defaultListeners->attach(events: $events);
        $serviceListener->attach($events);

        $moduleEvent = new ModuleEvent();
        $moduleEvent->setParam(name: 'ServiceManager', value: $container);

        $moduleManager = new ModuleManager(modules: $configuration['modules'], eventManager: $events);
        $moduleManager->setEvent(event: $moduleEvent);

        return $moduleManager;
    }
}
