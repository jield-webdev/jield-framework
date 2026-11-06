<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\Feature\AutoloaderProviderInterface;
use Jield\ModuleManager\Loader\AutoloaderFactory;
use Jield\ModuleManager\ModuleEvent;

use function method_exists;

class AutoloaderListener extends AbstractListener
{
    public function __invoke(ModuleEvent $e): void
    {
        $module = $e->getModule();
        if (
            !$module instanceof AutoloaderProviderInterface
            && !method_exists(object_or_class: \Jield\Stdlib\Value::objectOrString($module), method: 'getAutoloaderConfig')
        ) {
            return;
        }
        /** @var mixed $autoloaderConfig */
        $autoloaderConfig = \Jield\Stdlib\Value::call(\Jield\Stdlib\Value::present($module), 'getAutoloaderConfig');
        AutoloaderFactory::factory($autoloaderConfig);
    }
}
