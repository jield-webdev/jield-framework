<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\ModuleEvent;
use Jield\ModuleManager\ModuleManager;
use Jield\Mvc\Application;

use function method_exists;

class OnBootstrapListener extends AbstractListener
{
    /** @return void */
    public function __invoke(ModuleEvent $e): void
    {
        $module = $e->getModule();
        if (
            !$module instanceof BootstrapListenerInterface
            && !method_exists(object_or_class: \Jield\Stdlib\Value::objectOrString($module), method: 'onBootstrap')
        ) {
            return;
        }

        $moduleManager = $e->getTarget();
        /** @var mixed $events */
        $events = \Jield\Stdlib\Value::call(\Jield\Stdlib\Value::present(\Jield\Stdlib\Value::object($moduleManager)), 'getEventManager');
        /** @var mixed $sharedEvents */
        $sharedEvents = \Jield\Stdlib\Value::call($events, 'getSharedManager');
        \Jield\Stdlib\Value::call($sharedEvents, 'attach', [Application::class, ModuleManager::EVENT_BOOTSTRAP, [$module, 'onBootstrap']]);
    }
}
