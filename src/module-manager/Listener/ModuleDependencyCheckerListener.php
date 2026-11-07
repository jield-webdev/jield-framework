<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\Exception;
use Jield\ModuleManager\Feature\DependencyIndicatorInterface;
use Jield\ModuleManager\ModuleEvent;
use Jield\Stdlib\Value;
use Webmozart\Assert\Assert;

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
            || method_exists(object_or_class: Value::objectOrString($module), method: 'getModuleDependencies')
        ) {
            /** @mago-var mixed $dependencies */
            $dependencies = Value::call($module, 'getModuleDependencies');

            /** @mago-var mixed $dependencyModule */
            foreach (Assert::isIterable($dependencies) as $dependencyModule) {
                if (!isset($this->loaded[$dependencyModule])) {
                    throw new Exception\MissingDependencyModuleException(message: sprintf(
                        'Module "%s" depends on module "%s", which was not initialized before it',
                        $e->getModuleName(),
                        Value::scalar($dependencyModule),
                    ));
                }
            }
        }

        $this->loaded[$e->getModuleName()] = true;
    }
}
