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
        return new ConfigProvider()();
    }

    public function onBootstrap(EventInterface $e): void
    {
        /** @var mixed $app */
        $app = $e->getParam(name: 'application');
        /** @var ServiceManager $sm */
        $sm = \Jield\Stdlib\Value::call($app, 'getServiceManager');

        $setTitle = \Jield\Stdlib\Service::get($sm, InjectJavascriptAndCss::class);
        $setTitle->attach(events: \Jield\Stdlib\Value::instance(\Jield\Stdlib\Value::call($app, 'getEventManager'), \Laminas\EventManager\EventManagerInterface::class));
    }

    public function getModuleDependencies(): array
    {
        return ['Jield\\Authorize'];
    }
}
