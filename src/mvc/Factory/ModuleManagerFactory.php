<?php

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
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): ModuleManager
    {
        $configuration    = $container->get('ApplicationConfig');
        $listenerOptions  = new ListenerOptions($configuration['module_listener_options']);
        $defaultListeners = new DefaultListenerAggregate($listenerOptions);
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
        $defaultListeners->attach($events);
        $serviceListener->attach($events);

        $moduleEvent = new ModuleEvent();
        $moduleEvent->setParam('ServiceManager', $container);

        $moduleManager = new ModuleManager($configuration['modules'], $events);
        $moduleManager->setEvent($moduleEvent);

        return $moduleManager;
    }
}
