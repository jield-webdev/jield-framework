<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\Feature\InitProviderInterface;
use Jield\ModuleManager\ModuleEvent;
use Jield\ModuleManager\ModuleManagerInterface;
use Webmozart\Assert\Assert;

class InitTrigger extends AbstractListener
{
    public function __invoke(ModuleEvent $e): void
    {
        $module = $e->getModule();
        if ($module instanceof InitProviderInterface) {
            $module->init(Assert::isInstanceOf($e->getTarget(), ModuleManagerInterface::class));
        }
    }
}
