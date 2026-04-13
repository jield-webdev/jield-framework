<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\Feature\InitProviderInterface;
use Jield\ModuleManager\ModuleEvent;

class InitTrigger extends AbstractListener
{
    /** @return void */
    public function __invoke(ModuleEvent $e): void
    {
        $module = $e->getModule();
        if ($module instanceof InitProviderInterface) {
            $module->init($e->getTarget());
        }
    }
}
