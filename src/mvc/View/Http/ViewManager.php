<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Http;

use ArrayAccess;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\Stdlib\DispatchableInterface;
use Laminas\View\Model\ModelInterface;
use Laminas\View\View;
use Psr\Container\ContainerInterface;
use Traversable;

use function is_array;
use function is_string;

/**
 * Prepares the view layer
 *
 * Instantiates and configures all classes related to the view layer, including
 * the renderer (and its associated resolver(s) and helper manager), the view
 * object (and its associated rendering strategies), and the various MVC
 * strategies and listeners.
 *
 * Defines and manages the following services:
 *
 * - ViewHelperManager (also aliased to Laminas\View\HelperPluginManager)
 * - ViewTemplateMapResolver (also aliased to Laminas\View\Resolver\TemplateMapResolver)
 * - ViewTemplatePathStack (also aliased to Laminas\View\Resolver\TemplatePathStack)
 * - ViewResolver (also aliased to Laminas\View\Resolver\AggregateResolver and ResolverInterface)
 * - ViewRenderer (also aliased to Jield\Mvc\View\Renderer\PhpRenderer and RendererInterface)
 * - ViewPhpRendererStrategy (also aliased to Laminas\View\Strategy\PhpRendererStrategy)
 * - View (also aliased to Laminas\View\View)
 * - DefaultRenderingStrategy (also aliased to Jield\Mvc\View\Http\DefaultRenderingStrategy)
 * - ExceptionStrategy (also aliased to Jield\Mvc\View\Http\ExceptionStrategy)
 * - RouteNotFoundStrategy (also aliased to Jield\Mvc\View\Http\RouteNotFoundStrategy and 404Strategy)
 * - ViewModel
 */
class ViewManager extends AbstractListenerAggregate
{
    protected array $config;

    protected MvcEvent $event;

    protected ContainerInterface $services;

    protected ?View $view = null;
    protected ?ModelInterface $viewModel = null;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_BOOTSTRAP,
            listener: [$this, 'onBootstrap'],
            priority: 10_000,
        );
    }

    /**
     * Prepares the view layer
     *
     * @param MvcEvent $event
     * @return void
     */
    public function onBootstrap(MvcEvent $event): void
    {
        $application = $event->getApplication();
        $services = \Jield\Stdlib\Value::present($application)->getServiceManager();
        /** @var mixed $config */
        $config = $services->get('config');
        $events = \Jield\Stdlib\Value::present($application)->getEventManager();
        $sharedEvents = $events->getSharedManager();

        $this->config = isset($config['view_manager'])
        && (is_array($config['view_manager']) || $config['view_manager'] instanceof ArrayAccess)
            ? $config['view_manager']
            : [];
        $this->services = $services;
        $this->event = $event;

        /** @var mixed $routeNotFoundStrategy */
        $routeNotFoundStrategy = $services->get('HttpRouteNotFoundStrategy');
        /** @var mixed $exceptionStrategy */
        $exceptionStrategy = $services->get('HttpExceptionStrategy');
        /** @var mixed $mvcRenderingStrategy */
        $mvcRenderingStrategy = $services->get('HttpDefaultRenderingStrategy');

        $this->injectViewModelIntoPlugin();

        $injectTemplateListener = \Jield\Stdlib\Service::get($services, InjectTemplateListener::class);
        $createViewModelListener = new CreateViewModelListener();
        $injectViewModelListener = new InjectViewModelListener();

        $this->registerMvcRenderingStrategies($events);
        $this->registerViewStrategies(\Jield\Stdlib\Value::instance($events, \Laminas\EventManager\EventManager::class));

        \Jield\Stdlib\Value::call($routeNotFoundStrategy, 'attach', [$events]);
        \Jield\Stdlib\Value::call($exceptionStrategy, 'attach', [$events]);
        $events->attach(
            eventName: MvcEvent::EVENT_DISPATCH_ERROR,
            listener: [$injectViewModelListener, 'injectViewModel'],
            priority: -100,
        );
        $events->attach(
            eventName: MvcEvent::EVENT_RENDER_ERROR,
            listener: [$injectViewModelListener, 'injectViewModel'],
            priority: -100,
        );
        \Jield\Stdlib\Value::call($mvcRenderingStrategy, 'attach', [$events]);

        \Jield\Stdlib\Value::present($sharedEvents)->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: [$createViewModelListener, 'createViewModelFromArray'],
            priority: -80,
        );
        \Jield\Stdlib\Value::present($sharedEvents)->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: \Jield\Stdlib\Value::callback([$routeNotFoundStrategy, 'prepareNotFoundViewModel']),
            priority: -90,
        );
        \Jield\Stdlib\Value::present($sharedEvents)->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: [$createViewModelListener, 'createViewModelFromNull'],
            priority: -80,
        );
        \Jield\Stdlib\Value::present($sharedEvents)->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: [$injectTemplateListener, 'injectTemplate'],
            priority: -90,
        );
        \Jield\Stdlib\Value::present($sharedEvents)->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: [$injectViewModelListener, 'injectViewModel'],
            priority: -100,
        );
    }

    /**
     * Retrieves the View instance
     *
     * @return View
     */
    public function getView(): View
    {
        if ($this->view) {
            return $this->view;
        }

        $this->view = \Jield\Stdlib\Service::get($this->services, View::class);
        return $this->view;
    }

    /**
     * Configures the MvcEvent view model to ensure it has the template injected
     *
     * @return ModelInterface
     */
    public function getViewModel(): ModelInterface
    {
        if ($this->viewModel) {
            return $this->viewModel;
        }

        $this->viewModel = $model = $this->event->getViewModel();
        /** @var mixed $layoutTemplate */
        $layoutTemplate = \Jield\Stdlib\Value::call($this->services->get('HttpDefaultRenderingStrategy'), 'getLayoutTemplate');
        $model->setTemplate(\Jield\Stdlib\Value::nonEmptyString($layoutTemplate));

        return $this->viewModel;
    }

    /**
     * Register additional mvc rendering strategies
     *
     * If there is a "mvc_strategies" key of the view manager configuration, loop
     * through it. Pull each as a service from the service manager, and, if it
     * is a ListenerAggregate, attach it to the view, at priority 100. This
     * latter allows each to trigger before the default mvc rendering strategy,
     * and for them to trigger in the order they are registered.
     *
     * @return void
     */
    protected function registerMvcRenderingStrategies(EventManagerInterface $events): void
    {
        if (!isset($this->config['mvc_strategies'])) {
            return;
        }
        /** @var mixed $mvcStrategies */
        $mvcStrategies = $this->config['mvc_strategies'];
        if (is_string($mvcStrategies)) {
            $mvcStrategies = [$mvcStrategies];
        }
        if (!is_array($mvcStrategies) && !$mvcStrategies instanceof Traversable) {
            return;
        }

        /** @var mixed $mvcStrategy */
        foreach ($mvcStrategies as $mvcStrategy) {
            if (!is_string($mvcStrategy)) {
                continue;
            }

            /** @var mixed $listener */
            $listener = $this->services->get($mvcStrategy);
            if ($listener instanceof ListenerAggregateInterface) {
                $listener->attach(events: $events, priority: 100);
            }
        }
    }

    /**
     * Register additional view strategies
     *
     * If there is a "strategies" key of the view manager configuration, loop
     * through it. Pull each as a service from the service manager, and, if it
     * is a ListenerAggregate, attach it to the view, at priority 100. This
     * latter allows each to trigger before the default strategy, and for them
     * to trigger in the order they are registered.
     *
     * @return void
     */
    protected function registerViewStrategies(EventManager $events): void
    {
        if (!isset($this->config['strategies'])) {
            return;
        }
        /** @var mixed $strategies */
        $strategies = $this->config['strategies'];
        if (is_string($strategies)) {
            $strategies = [$strategies];
        }
        if (!is_array($strategies) && !$strategies instanceof Traversable) {
            return;
        }

        $view = $this->getView();

        /** @var mixed $strategy */
        foreach ($strategies as $strategy) {
            if (!is_string($strategy)) {
                continue;
            }

            /** @var mixed $listener */
            $listener = $this->services->get($strategy);
            if ($listener instanceof ListenerAggregateInterface) {
                $listener->attach(events: $events, priority: 100);
            }
        }
    }

    /**
     * Injects the ViewModel view helper with the root view model.
     */
    private function injectViewModelIntoPlugin(): void
    {
        $model = $this->getViewModel();
        $plugins = \Jield\Stdlib\Service::get($this->services, \Laminas\View\HelperPluginManager::class, 'ViewHelperManager');
        $plugin = \Jield\Stdlib\Service::get($plugins, \Laminas\View\Helper\ViewModel::class, 'viewmodel');
        $plugin->setRoot($model);
    }
}
