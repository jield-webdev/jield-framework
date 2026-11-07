<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\ServiceManager;

interface ServiceListenerInterface extends ListenerAggregateInterface
{
    /**
     * Provide metadata describing how to aggregate service/plugin manager configuration.
     *
     * - $serviceManager is the service name for the service/plugin manager.
     * - $key is the configuration key containing configuration for it.
     * - $moduleInterface is the interface indicating a configuration provider for it.
     * - $method is used for duck-typing configuration providers.
     *
     * @param ServiceManager|AbstractPluginManager|string $serviceManager Service name for service/plugin manager
     * @param string $key Configuration key
     * @param string $moduleInterface FQCN as string
     * @param string $method Method name
     * @return ServiceListenerInterface
     */
    public function addServiceManager(
        ServiceManager|AbstractPluginManager|string $serviceManager,
        string $key,
        string $moduleInterface,
        string $method,
    ): ServiceListenerInterface;

    /**
     * @param array $configuration
     * @return ServiceListenerInterface
     */
    public function setDefaultServiceConfig(array $configuration): ServiceListenerInterface;
}
