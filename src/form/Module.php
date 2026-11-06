<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\ModuleManager\Feature\FormElementProviderInterface;
use Jield\ModuleManager\Listener\ServiceListener;
use Jield\ModuleManager\ModuleManagerInterface;
use Jield\ModuleManager\Feature\InitProviderInterface;

final class Module implements InitProviderInterface
{
    /**
     * Return jield-form configuration for jield-mvc application.
     */
    public function getConfig(): array
    {
        $provider = new ConfigProvider();
        return [
            'service_manager' => $provider->getDependencyConfig(),
            'view_helpers' => $provider->getViewHelperConfig(),
        ];
    }

    /**
     * Register a specification for the FormElementManager with the ServiceListener.
     */
    public function init(ModuleManagerInterface $manager): void
    {
        /** @var mixed $event */
        $event = $manager->getEvent();
        /** @var mixed $container */
        $container = \Jield\Stdlib\Value::call($event, 'getParam', ['name' => 'ServiceManager']);
        
        $serviceListener = \Jield\Stdlib\Service::get(\Jield\Stdlib\Value::instance($container, \Psr\Container\ContainerInterface::class), \Jield\ModuleManager\Listener\ServiceListener::class, 'ServiceListener');

        $serviceListener->addServiceManager(
            serviceManager: FormElementManager::class,
            key: 'form_elements',
            moduleInterface: FormElementProviderInterface::class,
            method: 'getFormElementConfig',
        );
    }
}
