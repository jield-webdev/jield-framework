<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Traversable;

class ConfigFactory implements FactoryInterface
{
    /**
     * Create the application configuration service
     *
     * Retrieves the Module Manager from the service locator, and executes
     * {@link Jield\ModuleManager\ModuleManager::loadModules()}.
     *
     * It then retrieves the config listener from the module manager, and from
     * that the merged configuration.
     *
     * @param string $requestedName
     * @param null|array $options
     * @return array|Traversable
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $moduleManager = $container->get('ModuleManager');
        $moduleManager->loadModules();
        $moduleParams = $moduleManager->getEvent()->getParams();
        return $moduleParams['configListener']->getMergedConfig(false);
    }
}
