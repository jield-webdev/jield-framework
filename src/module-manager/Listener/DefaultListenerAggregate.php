<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\ModuleEvent;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Override;

class DefaultListenerAggregate extends AbstractListener implements ListenerAggregateInterface
{
    protected array $listeners = [];

    protected ?ConfigMergerInterface $configListener = null;

    /**
     * Attach one or more listeners
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $options = $this->getOptions();
        $configListener = $this->getConfigListener();
        $locatorRegistrationListener = new LocatorRegistrationListener(options: $options);

        // High priority, we assume module autoloading (for FooNamespace\Module
        // classes) should be available before anything else.
        // Register it only if use_laminas_loader config is true, however.
        if ($options->useLaminasLoader()) {
            $moduleLoaderListener = new ModuleLoaderListener(options: $options);
            $moduleLoaderListener->attach(events: $events);
            $this->listeners[] = $moduleLoaderListener;
        }

        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_LOAD_MODULE_RESOLVE,
            listener: new ModuleResolverListener(),
        );

        if ($options->useLaminasLoader()) {
            // High priority, because most other loadModule listeners will assume
            // the module's classes are available via autoloading
            // Register it only if use_laminas_loader config is true, however.
            $this->listeners[] = $events->attach(
                eventName: ModuleEvent::EVENT_LOAD_MODULE,
                listener: new AutoloaderListener(options: $options),
                priority: 9000,
            );
        }

        if ($options->getCheckDependencies()) {
            $this->listeners[] = $events->attach(
                eventName: ModuleEvent::EVENT_LOAD_MODULE,
                listener: new ModuleDependencyCheckerListener(),
                priority: 8000,
            );
        }

        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_LOAD_MODULE,
            listener: new InitTrigger(options: $options),
        );
        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_LOAD_MODULE,
            listener: new OnBootstrapListener(options: $options),
        );

        $locatorRegistrationListener->attach(events: $events);
        $configListener->attach(events: $events);
        $this->listeners[] = $locatorRegistrationListener;
        $this->listeners[] = $configListener;
    }

    /**
     * Detach all previously attached listeners
     */
    #[Override]
    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $key => $listener) {
            if ($listener instanceof ListenerAggregateInterface) {
                $listener->detach(events: $events);
                unset($this->listeners[$key]);
                continue;
            }

            $events->detach(listener: $listener);
            unset($this->listeners[$key]);
        }
    }

    /**
     * Get the config merger.
     */
    public function getConfigListener(): ConfigMergerInterface
    {
        if (!$this->configListener instanceof ConfigMergerInterface) {
            $this->setConfigListener(configListener: new ConfigListener(options: $this->getOptions()));
        }
        return $this->configListener;
    }

    /**
     * Set the config merger to use.
     */
    public function setConfigListener(ConfigMergerInterface $configListener): static
    {
        $this->configListener = $configListener;
        return $this;
    }
}
