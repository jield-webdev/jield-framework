<?php

declare(strict_types=1);

namespace Jield\Serializer;

use Jield\ModuleManager\ModuleManager;

/** @final */
class Module
{
    /**
     * Return default Jield-serializer configuration for Jield-mvc applications.
     *
     * @return array{service_manager: mixed}
     */
    public function getConfig(): array
    {
        $provider = new ConfigProvider();

        return [
            'service_manager' => $provider->getDependencyConfig(),
        ];
    }

    /**
     * Register a specification for the SerializerAdapterManager with the ServiceListener.
     *
     * @param ModuleManager $moduleManager
     * @return void
     */
    public function init(ModuleManager $moduleManager): void
    {
        $event           = $moduleManager->getEvent();
        $container       = $event->getParam(name: 'ServiceManager');
        $serviceListener = $container->get('ServiceListener');

        $serviceListener->addServiceManager(
            'SerializerAdapterManager',
            'serializers',
            'Jield\ModuleManager\Feature\SerializerProviderInterface',
            'getSerializerConfig'
        );
    }
}
