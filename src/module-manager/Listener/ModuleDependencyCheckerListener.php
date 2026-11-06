<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\Exception;
use Jield\ModuleManager\Feature\DependencyIndicatorInterface;
use Jield\ModuleManager\ModuleEvent;

use function method_exists;
use function sprintf;

class ModuleDependencyCheckerListener
{
    /** @var array of already loaded modules, indexed by module name */
    protected array $loaded = [];

    /** @throws Exception\MissingDependencyModuleException */
    public function __invoke(ModuleEvent $e): void
    {
        $module = $e->getModule();

        if (
            $module instanceof DependencyIndicatorInterface
            || method_exists(object_or_class: \Jield\Stdlib\Value::objectOrString($module), method: 'getModuleDependencies')
        ) {
            /** @var mixed $dependencies */
            $dependencies = \Jield\Stdlib\Value::call(\Jield\Stdlib\Value::present($module), 'getModuleDependencies');

            /** @var mixed $dependencyModule */
            foreach (\Jield\Stdlib\Value::iterable($dependencies) as $dependencyModule) {
                if (!isset($this->loaded[$dependencyModule])) {
                    throw new Exception\MissingDependencyModuleException(message: sprintf(
                        'Module "%s" depends on module "%s", which was not initialized before it',
                        $e->getModuleName(),
                        \Jield\Stdlib\Value::scalar($dependencyModule),
                    ));
                }
            }
        }

        $this->loaded[$e->getModuleName()] = true;
    }
}
