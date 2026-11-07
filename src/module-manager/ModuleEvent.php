<?php

declare(strict_types=1);

namespace Jield\ModuleManager;

use Laminas\EventManager\Event;

/**
 * Custom event for use with module manager
 * Composes Module objects
 *
 *
 * @extends \Laminas\EventManager\Event<object|string|null, array<string, mixed>>
 */
class ModuleEvent extends Event
{
    /**
     * Module events triggered by the event manager
     */
    public const string EVENT_MERGE_CONFIG = 'mergeConfig';
    public const string EVENT_LOAD_MODULES = 'loadModules';
    public const string EVENT_LOAD_MODULE_RESOLVE = 'loadModule.resolve';
    public const string EVENT_LOAD_MODULE = 'loadModule';
    public const string EVENT_LOAD_MODULES_POST = 'loadModules.post';

    protected object $module;

    protected string $moduleName;

    protected Listener\ConfigMergerInterface $configListener;

    /**
     * Get the name of a given module
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Set the name of a given module
     *
     * @throws Exception\InvalidArgumentException
     */
    public function setModuleName(string $moduleName): static
    {
        // Performance tweak, don't add it as param.
        $this->moduleName = $moduleName;

        return $this;
    }

    /**
     * Get module object
     */
    public function getModule(): ?object
    {
        return $this->module;
    }

    /**
     * Set module object to compose in this event
     *
     * @throws Exception\InvalidArgumentException
     */
    public function setModule(object $module): static
    {
        // Performance tweak, don't add it as param.
        $this->module = $module;

        return $this;
    }

    /**
     * Get the config listener
     */
    public function getConfigListener(): ?Listener\ConfigMergerInterface
    {
        return $this->configListener;
    }

    /**
     * Set module object to compose in this event
     */
    public function setConfigListener(Listener\ConfigMergerInterface $configListener): static
    {
        $this->setParam(name: 'configListener', value: $configListener);
        $this->configListener = $configListener;

        return $this;
    }
}
