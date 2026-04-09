<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\ModuleManager\Feature\FormElementProviderInterface;
use Jield\ModuleManager\Listener\ServiceListener;
use Jield\ModuleManager\ModuleManager;

final class Module
{
    /**
     * Return jield-form configuration for jield-mvc application.
     */
    public function getConfig(): array
    {
        $provider = new ConfigProvider();
        return [
            'service_manager' => $provider->getDependencyConfig(),
            'view_helpers'    => $provider->getViewHelperConfig(),
        ];
    }

    /**
     * Register a specification for the FormElementManager with the ServiceListener.
     */
    public function init(ModuleManager $moduleManager): void
    {
        $event     = $moduleManager->getEvent();
        $container = $event->getParam(name: 'ServiceManager');
        /** @var ServiceListener $serviceListener */
        $serviceListener = $container->get('ServiceListener');

        $serviceListener->addServiceManager(
            serviceManager: FormElementManager::class,
            key: 'form_elements',
            moduleInterface: FormElementProviderInterface::class,
            method: 'getFormElementConfig'
        );
    }
}
