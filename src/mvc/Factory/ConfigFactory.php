<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\ModuleManager\ModuleManager;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ConfigFactory implements FactoryInterface
{
    /**
     * Create the application configuration service
     *
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): array
    {
        $moduleManager = Service::get($container, ModuleManager::class, 'ModuleManager');
        $moduleManager->loadModules();
        $moduleParams = $moduleManager->getEvent()->getParams();
        return \Webmozart\Assert\Assert::isArray(Value::call(
            $moduleParams['configListener'],
            'getMergedConfig',
            [false],
        ));
    }
}
