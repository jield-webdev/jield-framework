<?php

declare(strict_types=1);

namespace DoctrineORMModule;

use Jield\DeveloperTools\ProfilerEvent;
use Laminas\EventManager\EventInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\ModuleManager\Feature\ControllerProviderInterface;
use Jield\ModuleManager\Feature\DependencyIndicatorInterface;
use Jield\ModuleManager\Feature\InitProviderInterface;
use Jield\ModuleManager\ModuleManagerInterface;

use function class_exists;

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
        $manager
            ->getEventManager()
            ->getSharedManager()
            ->attach(
                'doctrine',
                'loadCli.post',
                listener: static function (EventInterface $event): void {
                    $event
                        ->getParam(name: 'ServiceManager')
                        ->get(CliConfigurator::class)
                        ->configure($event->getTarget());
                },
                priority: 1,
            );

        // Initialize logger collector in DeveloperTools
        if (! class_exists(class: ProfilerEvent::class)) {
            return;
        }

        $manager
            ->getEventManager()
            ->attach(
                eventName: ProfilerEvent::EVENT_PROFILER_INIT,
                /** @param EventInterface $event */
                listener: static function (EventInterface $event): void {
                    $container = $event->getTarget()->getParam('ServiceManager');
                    $container->get('doctrine.sql_logger_collector.orm_default');
                },
            );
    }

    /**
     * {@inheritDoc}
     *
     * @return array<array-key,mixed>
     */
    public function getConfig(): array
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    /**
     * {@inheritDoc}
     *
     * @return array<array-key,mixed>
     */
    public function getControllerConfig(): array
    {
        return include __DIR__ . '/../config/controllers.config.php';
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
