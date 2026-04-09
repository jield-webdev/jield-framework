<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\Feature\InitProviderInterface;
use Jield\ModuleManager\ModuleEvent;

use function method_exists;

class InitTrigger extends AbstractListener
{
    /** @return void */
    public function __invoke(ModuleEvent $e): void
    {
        $module = $e->getModule();
        if (
            ! $module instanceof InitProviderInterface
            && ! method_exists(object_or_class: $module, method: 'init')
        ) {
            return;
        }

        $module->init(manager: $e->getTarget());
    }
}
