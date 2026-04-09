<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Jield\ModuleManager\Feature\LocatorRegisteredInterface;
use Jield\ModuleManager\ModuleEvent;
use Jield\ModuleManager\ModuleManager;
use Jield\Mvc\Application;
use Jield\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use Override;

use function end;
use function explode;

class LocatorRegistrationListener extends AbstractListener implements
    ListenerAggregateInterface
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
        if (! $e->getModule() instanceof LocatorRegisteredInterface) {
            return;
        }
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
        $events        = $moduleManager->getEventManager()->getSharedManager();

        if (! $events) {
            return;
        }

        // Shared instance for module manager
        $events->attach(
            Application::class,
            ModuleManager::EVENT_BOOTSTRAP,
            static function (MvcEvent $e) use ($moduleManager): void {
                $moduleClassName      = $moduleManager::class;
                $moduleClassNameArray = explode(separator: '\\', string: $moduleClassName);
                $moduleClassNameAlias = end(array: $moduleClassNameArray);
                $application          = $e->getApplication();
                /** @var ServiceManager $services */
                $services = $application->getServiceManager();
                if (! $services->has(id: $moduleClassName)) {
                        $services->setAlias(alias: $moduleClassName, target: $moduleClassNameAlias);
                }
            },
            1000
        );

        if (! $this->modules) {
            return;
        }

        // Attach to the bootstrap event if there are modules we need to process
        $events->attach(Application::class, ModuleManager::EVENT_BOOTSTRAP, [$this, 'onBootstrap'], 1000);
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
        $services = $application->getServiceManager();

        foreach ($this->modules as $module) {
            $moduleClassName = $module::class;
            if (! $services->has(id: $moduleClassName)) {
                $services->setService(name: $moduleClassName, service: $module);
            }
        }
    }

    /** {@inheritDoc} */
    #[Override]
    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULE, listener: [$this, 'onLoadModule']);
        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULES, listener: [$this, 'onLoadModules'], priority: -1000);
    }
}
