<?php

declare(strict_types=1);

namespace Jield\Bootstrap5;

use Jield\Bootstrap5\Event\InjectJavascriptAndCss;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\ModuleManager\Feature\DependencyIndicatorInterface;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\ServiceManager\ServiceManager;

final class Module implements ConfigProviderInterface, BootstrapListenerInterface, DependencyIndicatorInterface
{
    public function getConfig(): array
    {
        return new ConfigProvider()();
    }

    public function onBootstrap(EventInterface $e): void
    {
        $app = $e->getParam(name: 'application');
        /** @var ServiceManager $sm */
        $sm = Value::call($app, 'getServiceManager');

        $setTitle = Service::get($sm, InjectJavascriptAndCss::class);
        $setTitle->attach(events: Value::instance(
            Value::call($app, 'getEventManager'),
            EventManagerInterface::class,
        ));
    }

    public function getModuleDependencies(): array
    {
        return ['Jield\\Authorize'];
    }
}
