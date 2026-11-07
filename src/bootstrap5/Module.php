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
use Laminas\ServiceManager\ServiceManager;

final class Module implements ConfigProviderInterface, BootstrapListenerInterface, DependencyIndicatorInterface
{
    public function getConfig(): array
    {
        return new ConfigProvider()();
    }

    public function onBootstrap(EventInterface $e): void
    {
        /** @mago-var mixed $app */
        $app = $e->getParam(name: 'application');
        /** @var ServiceManager $sm */
        $sm = Value::call($app, 'getServiceManager');

        $setTitle = Service::get($sm, InjectJavascriptAndCss::class);
        $setTitle->attach(events: \Webmozart\Assert\Assert::isInstanceOf(Value::call($app, 'getEventManager'), \Laminas\EventManager\EventManagerInterface::class));
    }

    public function getModuleDependencies(): array
    {
        return ['Jield\\Authorize'];
    }
}
