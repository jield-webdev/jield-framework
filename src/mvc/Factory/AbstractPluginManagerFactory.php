<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/** @psalm-import-type ServiceManagerConfiguration from \Laminas\ServiceManager\ServiceManager */
abstract class AbstractPluginManagerFactory implements FactoryInterface
{
    /** @var class-string<AbstractPluginManager> */
    public const PLUGIN_MANAGER_CLASS = AbstractPluginManager::class;

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
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): AbstractPluginManager {
        $options = $options ?: [];
        $pluginManagerClass = static::PLUGIN_MANAGER_CLASS;
        /** @var ServiceManagerConfiguration $managerConfiguration */
        $managerConfiguration = $options;
        return \Jield\Stdlib\Value::instance(\Jield\Stdlib\Value::instantiate($pluginManagerClass, [$container, $managerConfiguration]), AbstractPluginManager::class);
    }
}
