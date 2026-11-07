<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\ModuleEvent;
use Jield\ModuleManager\ModuleManager;
use Jield\Mvc\Application;
use Jield\Stdlib\Value;
use Webmozart\Assert\Assert;

use function method_exists;

class OnBootstrapListener extends AbstractListener
{
    /** @return void */
    public function __invoke(ModuleEvent $e): void
    {
        $module = $e->getModule();
        if (
            !$module instanceof BootstrapListenerInterface
            && !method_exists(object_or_class: Value::objectOrString($module), method: 'onBootstrap')
        ) {
            return;
        }

        $moduleManager = $e->getTarget();
        /** @mago-var mixed $events */
        $events = Value::call(Assert::object($moduleManager), 'getEventManager');
        /** @mago-var mixed $sharedEvents */
        $sharedEvents = Value::call($events, 'getSharedManager');
        Value::call($sharedEvents, 'attach', [
            Application::class,
            ModuleManager::EVENT_BOOTSTRAP,
            [$module, 'onBootstrap'],
        ]);
    }
}
