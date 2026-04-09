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
            ! $module instanceof BootstrapListenerInterface
            && ! method_exists(object_or_class: $module, method: 'onBootstrap')
        ) {
            return;
        }

        $moduleManager = $e->getTarget();
        $events        = $moduleManager->getEventManager();
        $sharedEvents  = $events->getSharedManager();
        $sharedEvents->attach(Application::class, ModuleManager::EVENT_BOOTSTRAP, [$module, 'onBootstrap']);
    }
}
