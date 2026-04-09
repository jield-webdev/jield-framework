<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Jield\ModuleManager\ModuleEvent;
use Override;

class DefaultListenerAggregate extends AbstractListener implements
    ListenerAggregateInterface
{
    /** @var array */
    protected array $listeners = [];

    /** @var ConfigMergerInterface */
    protected ConfigMergerInterface $configListener;

    /**
     * Attach one or more listeners
     *
     * @param int $priority
     * @return void
     */
    #[Override]
    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        $options                     = $this->getOptions();
        $configListener              = $this->getConfigListener();
        $locatorRegistrationListener = new LocatorRegistrationListener(options: $options);

        // High priority, we assume module autoloading (for FooNamespace\Module
        // classes) should be available before anything else.
        // Register it only if use_jield_loader config is true, however.
        if ($options->useJieldLoader()) {
            $moduleLoaderListener = new ModuleLoaderListener(options: $options);
            $moduleLoaderListener->attach(events: $events);
            $this->listeners[] = $moduleLoaderListener;
        }
        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULE_RESOLVE, listener: new ModuleResolverListener());

        if ($options->useJieldLoader()) {
            // High priority, because most other loadModule listeners will assume
            // the module's classes are available via autoloading
            // Register it only if use_jield_loader config is true, however.
            $this->listeners[] = $events->attach(
                eventName: ModuleEvent::EVENT_LOAD_MODULE,
                listener: new AutoloaderListener(options: $options),
                priority: 9000
            );
        }

        if ($options->getCheckDependencies()) {
            $this->listeners[] = $events->attach(
                eventName: ModuleEvent::EVENT_LOAD_MODULE,
                listener: new ModuleDependencyCheckerListener(),
                priority: 8000
            );
        }

        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULE, listener: new InitTrigger(options: $options));
        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULE, listener: new OnBootstrapListener(options: $options));

        $locatorRegistrationListener->attach(events: $events);
        $configListener->attach(events: $events);
        $this->listeners[] = $locatorRegistrationListener;
        $this->listeners[] = $configListener;
        return $this;
    }

    /**
     * Detach all previously attached listeners
     *
     * @return void
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
     *
     * @return ConfigMergerInterface
     */
    public function getConfigListener(): ConfigMergerInterface
    {
        if (! $this->configListener instanceof ConfigMergerInterface) {
            $this->setConfigListener(configListener: new ConfigListener(options: $this->getOptions()));
        }
        return $this->configListener;
    }

    /**
     * Set the config merger to use.
     *
     * @return DefaultListenerAggregate
     */
    public function setConfigListener(ConfigMergerInterface $configListener): static
    {
        $this->configListener = $configListener;
        return $this;
    }
}
