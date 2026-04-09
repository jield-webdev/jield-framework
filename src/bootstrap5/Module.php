<?php

namespace Jield\Bootstrap5;

use Jield\Bootstrap5\Event\InjectJavascriptAndCss;
use Laminas\EventManager\EventInterface;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\ModuleManager\Feature\DependencyIndicatorInterface;
use Laminas\ServiceManager\ServiceManager;

/**
 * Class Module
 * @package Jield\Bootstrap5
 */
final class Module implements ConfigProviderInterface, BootstrapListenerInterface, DependencyIndicatorInterface
{
    public function getConfig(): array
    {
        return include __DIR__ . '/../../config/bootstrap5/module.config.php';
    }

    public function onBootstrap(EventInterface $e): void
    {
        $app = $e->getParam(name: 'application');
        /** @var ServiceManager $sm */
        $sm = $app->getServiceManager();

        $setTitle = $sm->get(id: InjectJavascriptAndCss::class);
        $setTitle->attach(events: $app->getEventManager());
    }

    public function getModuleDependencies(): array
    {
        return ['AssetManager'];
    }
}
