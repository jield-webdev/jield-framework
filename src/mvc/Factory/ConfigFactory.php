<?php

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ConfigFactory implements FactoryInterface
{
    /**
     * Create the application configuration service
     *
     * @param string $requestedName
     * @param null|array $options
     * @return array
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): array
    {
        $moduleManager = $container->get('ModuleManager');
        $moduleManager->loadModules();
        $moduleParams = $moduleManager->getEvent()->getParams();
        return $moduleParams['configListener']->getMergedConfig(false);
    }
}
