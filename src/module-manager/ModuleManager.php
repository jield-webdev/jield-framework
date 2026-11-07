<?php

declare(strict_types=1);

namespace Jield\ModuleManager;

use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use Override;
use Traversable;

use function is_array;
use function is_object;
use function sprintf;

class ModuleManager implements ModuleManagerInterface
{
    public const string EVENT_BOOTSTRAP = 'bootstrap';

    protected array $loadedModules = [];

    protected ?EventManagerInterface $events = null;

    protected ?ModuleEvent $event = null;

    protected int $loadFinished;

    protected array|Traversable $modules = [];

    /**
     * True if modules have already been loaded
     *
     * @var bool
     */
    protected bool $modulesAreLoaded = false;

    public function __construct(Traversable|array $modules, ?EventManagerInterface $eventManager = null)
    {
        $this->setModules(modules: $modules);
        if ($eventManager instanceof EventManagerInterface) {
            $this->setEventManager(eventManager: $eventManager);
        }
    }

    /**
     * Handle the loadModules event
     *
     * @return void
     */
    public function onLoadModules(): void
    {
        if (true === $this->modulesAreLoaded) {
            return;
        }

        /** @mago-var mixed $moduleName */
        foreach ($this->getModules() as $moduleName) {
            $this->loadModule(moduleName: \Webmozart\Assert\Assert::string($moduleName));
        }

        $this->modulesAreLoaded = true;
    }

    /**
     * Load the provided modules.
     *
     * @triggers loadModules
     * @triggers loadModules.post
     * @return static
     */
    #[Override]
    public function loadModules(): static
    {
        if (true === $this->modulesAreLoaded) {
            return $this;
        }

        $events = $this->getEventManager();
        $event = $this->getEvent();
        $event->setName(name: ModuleEvent::EVENT_LOAD_MODULES);

        $events->triggerEvent(event: $event);

        /**
         * Having a dedicated .post event abstracts the complexity of priorities from the user.
         * Users can attach to the .post event and be sure that important
         * things like config merging are complete without having to worry if
         * they set a low enough priority.
         */
        $event->setName(name: ModuleEvent::EVENT_LOAD_MODULES_POST);
        $events->triggerEvent(event: $event);

        return $this;
    }

    /**
     * Load a specific module by name.
     *
     * @param string $moduleName
     * @return mixed Module's Module class
     * @throws Exception\RuntimeException
     * @triggers loadModule.resolve
     * @triggers loadModule
     */
    #[Override]
    public function loadModule(string $moduleName): mixed
    {
        if (isset($this->loadedModules[$moduleName])) {
            return $this->loadedModules[$moduleName];
        }

        /*
         * Keep track of nested module loading using the $loadFinished
         * property.
         *
         * Increment the value for each loadModule() call and then decrement
         * once the loading process is complete.
         *
         * To load a module, we clone the event if we are inside a nested
         * loadModule() call, and use the original event otherwise.
         */
        if (!isset($this->loadFinished)) {
            $this->loadFinished = 0;
        }

        $event = $this->loadFinished > 0 ? clone $this->getEvent() : $this->getEvent();
        $event->setModuleName(moduleName: $moduleName);

        $this->loadFinished++;

        /** @mago-var mixed $module */
        $module = $this->loadModuleByName(event: $event);
        $event->setModule(module: \Webmozart\Assert\Assert::object($module));
        $event->setName(name: ModuleEvent::EVENT_LOAD_MODULE);

        $this->loadedModules[$moduleName] = $module;
        $this->getEventManager()->triggerEvent(event: $event);

        $this->loadFinished--;

        return $moduleName;
    }

    /**
     * Load a module with the name
     *
     * @return mixed                            module instance
     * @throws Exception\RuntimeException
     */
    protected function loadModuleByName(ModuleEvent $event): mixed
    {
        $event->setName(name: ModuleEvent::EVENT_LOAD_MODULE_RESOLVE);
        $result = $this->getEventManager()->triggerEventUntil(callback: static fn($r): bool => is_object(
            value: $r,
        ), event: $event);

        /** @mago-var mixed $module */
        $module = $result->last();

        if (!is_object(value: $module)) {
            throw new Exception\RuntimeException(message: sprintf(
                'Module (%s) could not be initialized.',
                $event->getModuleName(),
            ));
        }

        return $module;
    }

    /**
     * Get an array of the loaded modules.
     *
     * @param bool $loadModules If true, load modules if they're not already
     * @return array An array of Module objects, keyed by module name
     */
    #[Override]
    public function getLoadedModules(bool $loadModules = false): array
    {
        if (true === $loadModules) {
            $this->loadModules();
        }

        return $this->loadedModules;
    }

    /**
     * Get an instance of a module class by the module name
     *
     * @param string $moduleName
     * @return mixed
     */
    public function getModule(string $moduleName): mixed
    {
        return $this->loadedModules[$moduleName] ?? null;
    }

    /**
     * Get the array of module names that this manager should load.
     *
     * @return array
     */
    #[Override]
    public function getModules(): array
    {
        return is_array($this->modules) ? $this->modules : iterator_to_array($this->modules);
    }

    /**
     * Set an array or Traversable of module names that this module manager should load.
     *
     * @param mixed $modules array or Traversable of module names
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function setModules(mixed $modules): static
    {
        if (is_iterable($modules)) {
            $this->modules = $modules;
        } else {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Parameter to %s\'s %s method must be an array or implement the Traversable interface',
                self::class,
                __METHOD__,
            ));
        }
        return $this;
    }

    /**
     * Get the module event
     *
     * @return ModuleEvent
     */
    public function getEvent(): ModuleEvent
    {
        if (!$this->event instanceof ModuleEvent) {
            $this->setEvent(event: new ModuleEvent());
        }
        return $this->event;
    }

    /**
     * Set the module event
     *
     * @return static
     */
    public function setEvent(ModuleEvent $event): static
    {
        $event->setTarget(target: $this);
        $this->event = $event;
        return $this;
    }

    /**
     * Set the event manager instance used by this module manager.
     *
     * @return void
     */
    #[Override]
    public function setEventManager(EventManagerInterface $eventManager): void
    {
        $eventManager->setIdentifiers(identifiers: [
            self::class,
            static::class,
            'module_manager',
        ]);
        $this->events = $eventManager;
        $this->attachDefaultListeners(events: $eventManager);
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    #[Override]
    public function getEventManager(): EventManagerInterface
    {
        if (!$this->events instanceof EventManagerInterface) {
            $this->setEventManager(eventManager: new EventManager());
        }
        return $this->events;
    }

    /**
     * Register the default event listeners
     *
     * @param EventManagerInterface $events
     */
    protected function attachDefaultListeners(EventManagerInterface $events): void
    {
        $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULES, listener: $this->onLoadModules(...));
    }
}
