<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\ModuleEvent;
use Jield\ModuleManager\ModuleManager;
use Jield\Mvc\Application;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Laminas\ServiceManager\ServiceManager;
use Override;

use function end;
use function explode;

class LocatorRegistrationListener extends AbstractListener implements ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    /** @var array */
    protected array $modules = [];

    /**
     * Check each loaded module to see if it implements LocatorRegistered. If it
     * does, we add it to an internal array for later.
     *
     * @return void
     */
    public function onLoadModule(ModuleEvent $e): void
    {
        $this->modules[] = $e->getModule();
    }

    /**
     * Once all the modules are loaded, loop
     *
     * @return void
     */
    public function onLoadModules(ModuleEvent $e): void
    {
        $moduleManager = $e->getTarget();
        /** @var mixed $events */
        $events = \Jield\Stdlib\Value::call(\Jield\Stdlib\Value::call(\Jield\Stdlib\Value::present(\Jield\Stdlib\Value::object($moduleManager)), 'getEventManager'), 'getSharedManager');

        if (!$events) {
            return;
        }

        // Shared instance for module manager
        \Jield\Stdlib\Value::call($events, 'attach', [Application::class, ModuleManager::EVENT_BOOTSTRAP, static function (MvcEvent $e) use ($moduleManager): void {
                $moduleClassName = $moduleManager::class;
                $moduleClassNameArray = explode(separator: '\\', string: $moduleClassName);
                $moduleClassNameAlias = end(array: $moduleClassNameArray);
                $application = $e->getApplication();
                /** @var ServiceManager $services */
                $services = \Jield\Stdlib\Value::present($application)->getServiceManager();
                if (!$services->has($moduleClassName)) {
                    $services->setAlias(alias: $moduleClassName, target: $moduleClassNameAlias);
                }
            }, 1000]);

        if (!$this->modules) {
            return;
        }

        // Attach to the bootstrap event if there are modules we need to process
        \Jield\Stdlib\Value::call($events, 'attach', [Application::class, ModuleManager::EVENT_BOOTSTRAP, [$this, 'onBootstrap'], 1000]);
    }

    /**
     * This is ran during the MVC bootstrap event because it requires access to
     * the DI container.
     *
     * @TODO: Check the application / locator / etc a bit better to make sure
     * the env looks how we're expecting it to?
     * @return void
     */
    public function onBootstrap(MvcEvent $e): void
    {
        $application = $e->getApplication();
        /** @var ServiceManager $services */
        $services = \Jield\Stdlib\Value::present($application)->getServiceManager();

        /** @var mixed $module */
        foreach ($this->modules as $module) {
            /** @var mixed $moduleClassName */
            $moduleClassName = $module::class;
            if (!$services->has(\Jield\Stdlib\Value::string($moduleClassName))) {
                $services->setService(name: \Jield\Stdlib\Value::string($moduleClassName), service: $module);
            }
        }
    }

    /** {@inheritDoc} */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULE, listener: [
            $this,
            'onLoadModule',
        ]);
        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_LOAD_MODULES,
            listener: [$this, 'onLoadModules'],
            priority: -1000,
        );
    }
}
