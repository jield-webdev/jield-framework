<?php

declare(strict_types=1);

namespace Jield\Bootstrap5;

use Jield\Bootstrap5\Event\InjectJavascriptAndCss;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\ModuleManager\Feature\DependencyIndicatorInterface;
use Laminas\EventManager\EventInterface;
use Laminas\ServiceManager\ServiceManager;

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
        return [\Jield\Authorize\Module::class];
    }
}
