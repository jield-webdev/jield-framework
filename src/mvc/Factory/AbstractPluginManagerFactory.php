<?php

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

abstract class AbstractPluginManagerFactory implements FactoryInterface
{
    public const PLUGIN_MANAGER_CLASS = 'AbstractPluginManager';

    /**
     * Create and return a plugin manager.
     *
     * Classes that extend this should provide a valid class for
     * the PLUGIN_MANGER_CLASS constant.
     *
     * @param string $requestedName
     * @param  null|array $options
     * @return AbstractPluginManager
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): AbstractPluginManager
    {
        $options            = $options ?: [];
        $pluginManagerClass = static::PLUGIN_MANAGER_CLASS;
        return new $pluginManagerClass($container, $options);
    }
}
