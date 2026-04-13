<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\ModuleEvent;
use Jield\Mvc\Loader\ModuleAutoloader;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Override;
use function file_exists;

class ModuleLoaderListener extends AbstractListener implements ListenerAggregateInterface
{
    protected ModuleAutoloader $moduleLoader;

    protected bool $generateCache;

    protected array $callbacks = [];

    /**
     * Creates an instance of the ModuleAutoloader and injects the module paths
     * into it.
     */
    public function __construct(?ListenerOptions $options = null)
    {
        parent::__construct(options: $options);

        $this->generateCache = $this->options->getModuleMapCacheEnabled();
        $this->moduleLoader  = new ModuleAutoloader($this->options->getModulePaths());

        if ($this->hasCachedClassMap()) {
            $this->generateCache = false;
            $this->moduleLoader->setModuleClassMap($this->getCachedConfig());
        }
    }

    /** {@inheritDoc} */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->callbacks[] = $events->attach(
            eventName: ModuleEvent::EVENT_LOAD_MODULES,
            listener: [$this->moduleLoader, 'register'],
            priority: 9000
        );

        if ($this->generateCache) {
            $this->callbacks[] = $events->attach(
                eventName: ModuleEvent::EVENT_LOAD_MODULES_POST,
                listener: [$this, 'onLoadModulesPost']
            );
        }
    }

    /** {@inheritDoc} */
    #[Override]
    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->callbacks as $index => $callback) {
            if ($events->detach(listener: $callback)) {
                unset($this->callbacks[$index]);
            }
        }
    }

    /** @return bool */
    protected function hasCachedClassMap(): bool
    {
        return $this->options->getModuleMapCacheEnabled()
            && file_exists(filename: $this->options->getModuleMapCacheFile());
    }

    /** @return array */
    protected function getCachedConfig(): array
    {
        return include $this->options->getModuleMapCacheFile();
    }

    /**
     * Unregisters the ModuleLoader and generates the module class map cache.
     */
    public function onLoadModulesPost(ModuleEvent $event): void
    {
        $this->moduleLoader->unregister();
        $this->writeArrayToFile(
            filePath: $this->options->getModuleMapCacheFile(),
            array: $this->moduleLoader->getModuleClassMap()
        );
    }
}
