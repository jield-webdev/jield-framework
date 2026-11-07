<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\Feature\AutoloaderProviderInterface;
use Jield\ModuleManager\Loader\AutoloaderFactory;
use Jield\ModuleManager\ModuleEvent;

use Jield\Stdlib\Value;
use function method_exists;

class AutoloaderListener extends AbstractListener
{
    public function __invoke(ModuleEvent $e): void
    {
        $module = $e->getModule();
        if (
            !$module instanceof AutoloaderProviderInterface
            && !method_exists(
                object_or_class: Value::objectOrString($module),
                method: 'getAutoloaderConfig',
            )
        ) {
            return;
        }
        $autoloaderConfig = Value::call(Value::present($module), 'getAutoloaderConfig');
        AutoloaderFactory::factory($autoloaderConfig);
    }
}
