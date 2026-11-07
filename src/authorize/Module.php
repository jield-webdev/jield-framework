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
use Jield\Stdlib\Service;
use Laminas\EventManager\EventInterface;
use Laminas\ServiceManager\ServiceManager;
use Webmozart\Assert\Assert;

/**
 * BjyAuthorize Module
 */
class Module implements BootstrapListenerInterface, ConfigProviderInterface, DependencyIndicatorInterface
{
    public function onBootstrap(EventInterface $e): void
    {
        /** @var ApplicationInterface $app */
        $app = $e->getTarget();
        /** @var ServiceManager $serviceManager */
        $serviceManager = $app->getServiceManager();
        $config = Assert::isArray($serviceManager->get(Config::class));
        /** @var UnauthorizedStrategy $strategy */
        $strategy = $serviceManager->get($config['unauthorized_strategy']);
        /** @var AbstractGuard[] $guards */
        $guards = Assert::isArray($serviceManager->get(Guards::class));

        foreach ($guards as $guard) {
            $guard->attach(events: $app->getEventManager());
        }

        $strategy->attach(events: $app->getEventManager());

        $ruleWithAssertion = Service::get($serviceManager, RulesWithAssertion::class);
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
            'Laminas\\Cache',
        ];
    }
}
