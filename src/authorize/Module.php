<?php

declare(strict_types=1);

namespace Jield\Authorize;

use Jield\Authorize\Guard\AbstractGuard;
use Jield\Authorize\Rule\RulesWithAssertion;
use Jield\Authorize\View\UnauthorizedStrategy;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\ModuleManager\Feature\DependencyIndicatorInterface;
use Jield\Mvc\ApplicationInterface;
use Laminas\EventManager\EventInterface;
use Laminas\ServiceManager\ServiceManager;

/**
 * BjyAuthorize Module
 */
class Module implements
    BootstrapListenerInterface,
    ConfigProviderInterface,
    DependencyIndicatorInterface
{
    public function onBootstrap(EventInterface $e): void
    {
        /** @var ApplicationInterface $app */
        $app = $e->getTarget();
        /** @var ServiceManager $serviceManager */
        $serviceManager = $app->getServiceManager();
        $config         = $serviceManager->get(id: Config::class);
        /** @var UnauthorizedStrategy $strategy */
        $strategy = $serviceManager->get(id: $config['unauthorized_strategy']);
        /** @var AbstractGuard[] $guards */
        $guards = $serviceManager->get(id: Guards::class);

        foreach ($guards as $guard) {
            $guard->attach(events: $app->getEventManager());
        }

        $strategy->attach(events: $app->getEventManager());

        $ruleWithAssertion = $serviceManager->get(id: RulesWithAssertion::class);
        $ruleWithAssertion->attach(events: $app->getEventManager());
    }

    public function getConfig(): array
    {
        $configProvider = new ConfigProvider();

        return $configProvider();
    }

    public function getModuleDependencies(): array
    {
        return [
            \Laminas\Cache\ConfigProvider::class,
        ];
    }
}
