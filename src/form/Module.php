<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\ModuleManager\Feature\FormElementProviderInterface;
use Jield\ModuleManager\Feature\InitProviderInterface;
use Jield\ModuleManager\Listener\ServiceListener;
use Jield\ModuleManager\ModuleManagerInterface;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

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
        $container = Value::call($event, 'getParam', ['name' => 'ServiceManager']);

        $serviceListener = Service::get(
            Value::instance($container, ContainerInterface::class),
            \Jield\ModuleManager\Listener\ServiceListener::class,
            'ServiceListener',
        );

        $serviceListener->addServiceManager(
            serviceManager: FormElementManager::class,
            key: 'form_elements',
            moduleInterface: FormElementProviderInterface::class,
            method: 'getFormElementConfig',
        );
    }
}
