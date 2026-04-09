<?php

declare(strict_types=1);

namespace Jield\ModuleManager;

use Laminas\EventManager\Event;

use function gettype;
use function is_object;
use function is_string;
use function sprintf;

/**
 * Custom event for use with module manager
 * Composes Module objects
 */
class ModuleEvent extends Event
{
    /**
     * Module events triggered by eventmanager
     */
    public const string EVENT_MERGE_CONFIG        = 'mergeConfig';
    public const string EVENT_LOAD_MODULES        = 'loadModules';
    public const string EVENT_LOAD_MODULE_RESOLVE = 'loadModule.resolve';
    public const string EVENT_LOAD_MODULE         = 'loadModule';
    public const string EVENT_LOAD_MODULES_POST   = 'loadModules.post';

    /** @var mixed */
    protected mixed $module;

    /** @var string */
    protected string $moduleName;

    /** @var Listener\ConfigMergerInterface */
    protected Listener\ConfigMergerInterface $configListener;

    /**
     * Get the name of a given module
     *
     * @return string
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Set the name of a given module
     *
     * @param string $moduleName
     * @return ModuleEvent
     *@throws Exception\InvalidArgumentException
     */
    public function setModuleName(string $moduleName): static
    {
        if (! is_string(value: $moduleName)) {
            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    '%s expects a string as an argument; %s provided',
                    __METHOD__,
                    gettype(value: $moduleName)
                )
            );
        }
        // Performance tweak, don't add it as param.
        $this->moduleName = $moduleName;

        return $this;
    }

    /**
     * Get module object
     *
     * @return null|object
     */
    public function getModule(): ?object
    {
        return $this->module;
    }

    /**
     * Set module object to compose in this event
     *
     * @param object $module
     * @return ModuleEvent
     *@throws Exception\InvalidArgumentException
     */
    public function setModule(object $module): static
    {
        if (! is_object(value: $module)) {
            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    '%s expects a module object as an argument; %s provided',
                    __METHOD__,
                    gettype(value: $module)
                )
            );
        }
        // Performance tweak, don't add it as param.
        $this->module = $module;

        return $this;
    }

    /**
     * Get the config listener
     *
     * @return null|Listener\ConfigMergerInterface
     */
    public function getConfigListener(): ?Listener\ConfigMergerInterface
    {
        return $this->configListener;
    }

    /**
     * Set module object to compose in this event
     *
     * @return ModuleEvent
     */
    public function setConfigListener(Listener\ConfigMergerInterface $configListener): static
    {
        $this->setParam(name: 'configListener', value: $configListener);
        $this->configListener = $configListener;

        return $this;
    }
}
