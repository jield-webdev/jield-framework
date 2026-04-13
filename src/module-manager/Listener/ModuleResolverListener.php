<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Generator;
use Jield\ModuleManager\ModuleEvent;
use function class_exists;
use function in_array;
use function sprintf;

class ModuleResolverListener extends AbstractListener
{
    /**
     * Class names that are invalid as module classes, due to inability to instantiate.
     *
     * @var string[]
     */
    protected array $invalidClassNames
        = [
            Generator::class,
        ];

    /**
     * @return object|false False if module class does not exist
     */
    public function __invoke(ModuleEvent $e): object|false
    {
        $moduleName = $e->getModuleName();

        $class = sprintf('%s\Module', $moduleName);
        if (class_exists(class: $class)) {
            return new $class();
        }

        $class = sprintf('%s\ConfigProvider', $moduleName);
        if (class_exists(class: $class)) {
            return new $class();
        }

        if (
            class_exists(class: $moduleName)
            && !in_array(needle: $moduleName, haystack: $this->invalidClassNames, strict: true)
        ) {
            return new $moduleName();
        }

        return false;
    }
}
