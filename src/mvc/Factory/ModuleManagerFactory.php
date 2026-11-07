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
use Jield\ModuleManager\Listener\ServiceListener;
use Jield\ModuleManager\ModuleEvent;
use Jield\ModuleManager\ModuleManager;
use Jield\Stdlib\Service;
use Laminas\EventManager\EventManagerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

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
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ModuleManager {
        /** @mago-var mixed $configuration */
        $configuration = $container->get('ApplicationConfig');
        $listenerOptions = new ListenerOptions(\Jield\Stdlib\Value::options(\Jield\Stdlib\Value::accessible($configuration)['module_listener_options']));
        $defaultListeners = new DefaultListenerAggregate($listenerOptions);
        $serviceListener = Service::get($container, ServiceListener::class, 'ServiceListener');

        $serviceListener->addServiceManager(
            Assert::isInstanceOf($container, ServiceManager::class),
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

        $events = Service::get($container, EventManagerInterface::class, 'EventManager');
        $defaultListeners->attach($events);
        $serviceListener->attach($events);

        $moduleEvent = new ModuleEvent();
        $moduleEvent->setParam('ServiceManager', $container);

        $moduleManager = new ModuleManager(Assert::isIterable(\Jield\Stdlib\Value::accessible($configuration)['modules']), $events);
        $moduleManager->setEvent($moduleEvent);

        return $moduleManager;
    }
}
