<?php

declare(strict_types=1);

namespace DoctrineORMModule;

use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\ModuleManager\Feature\ControllerProviderInterface;
use Jield\ModuleManager\Feature\DependencyIndicatorInterface;
use Jield\ModuleManager\Feature\InitProviderInterface;
use Jield\ModuleManager\ModuleManagerInterface;
use Laminas\EventManager\EventInterface;

/**
 * Base module for Doctrine ORM.
 */
final class Module implements
    ControllerProviderInterface,
    ConfigProviderInterface,
    DependencyIndicatorInterface,
    InitProviderInterface
{
    public function init(ModuleManagerInterface $manager): void
    {
        // Initialize the console
        \Jield\Stdlib\Value::present($manager
            ->getEventManager()
            ->getSharedManager())
            ->attach(
                'doctrine',
                'loadCli.post',
                listener: static function (EventInterface $event): void {
                    \Jield\Stdlib\Value::call(\Jield\Stdlib\Value::call($event
                        ->getParam(name: 'ServiceManager'), 'get', [CliConfigurator::class]), 'configure', [$event->getTarget()]);
                },
                priority: 1,
            );
    }

    /**
     * {@inheritDoc}
     *
     * @return array<array-key,mixed>
     */
    public function getConfig(): array
    {
        /** @var array<array-key, mixed> $config */
        $config = include __DIR__ . '/../../config/docrine-orm-module/module.config.php';
        return $config;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<array-key,mixed>
     */
    public function getControllerConfig(): array
    {
        /** @var array<array-key, mixed> $config */
        $config = include __DIR__ . '/../../config/docrine-orm-module/controllers.config.php';
        return $config;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public function getModuleDependencies(): array
    {
        return ['DoctrineModule'];
    }
}
