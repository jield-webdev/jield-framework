<?php

namespace Jield\Mvc\View\Http;

use ArrayAccess;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\View\Http\InjectTemplateListener;
use Laminas\ServiceManager\ServiceManager;
use Jield\Stdlib\DispatchableInterface;
use Laminas\View\Model\ModelInterface;
use Laminas\View\View;
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
 * - ViewRenderer (also aliased to Laminas\View\Renderer\PhpRenderer and RendererInterface)
 * - ViewPhpRendererStrategy (also aliased to Laminas\View\Strategy\PhpRendererStrategy)
 * - View (also aliased to Laminas\View\View)
 * - DefaultRenderingStrategy (also aliased to Jield\Mvc\View\Http\DefaultRenderingStrategy)
 * - ExceptionStrategy (also aliased to Jield\Mvc\View\Http\ExceptionStrategy)
 * - RouteNotFoundStrategy (also aliased to Jield\Mvc\View\Http\RouteNotFoundStrategy and 404Strategy)
 * - ViewModel
 */
class ViewManager extends AbstractListenerAggregate
{
    /** @var object application configuration service */
    protected object $config;

    /** @var MvcEvent */
    protected MvcEvent $event;

    /** @var ServiceManager */
    protected ServiceManager $services;

    /**
     * Various properties representing strategies and objects instantiated and
     * configured by the view manager
     *
     * @var mixed
     */
    protected mixed $helperManager;
    /** @var mixed */
    protected mixed $mvcRenderingStrategy;
    /** @var mixed */
    protected mixed $renderer;
    /** @var mixed */
    protected mixed $rendererStrategy;
    /** @var mixed */
    protected mixed $resolver;
    /** @var mixed */
    protected mixed $view;
    /** @var mixed */
    protected mixed $viewModel;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_BOOTSTRAP, listener: [$this, 'onBootstrap'], priority: 10000);
    }

    /**
     * Prepares the view layer
     *
     * @param MvcEvent $event
     * @return void
     */
    public function onBootstrap(MvcEvent $event): void
    {
        $application  = $event->getApplication();
        $services     = $application->getServiceManager();
        $config       = $services->get('config');
        $events       = $application->getEventManager();
        $sharedEvents = $events->getSharedManager();

        $this->config   = isset($config['view_manager'])
            && (is_array(value: $config['view_manager'])
            || $config['view_manager'] instanceof ArrayAccess)
                ? $config['view_manager']
                : [];
        $this->services = $services;
        $this->event    = $event;

        $routeNotFoundStrategy = $services->get('HttpRouteNotFoundStrategy');
        $exceptionStrategy     = $services->get('HttpExceptionStrategy');
        $mvcRenderingStrategy  = $services->get('HttpDefaultRenderingStrategy');

        $this->injectViewModelIntoPlugin();

        $injectTemplateListener  = $services->get(InjectTemplateListener::class);
        $createViewModelListener = new CreateViewModelListener();
        $injectViewModelListener = new InjectViewModelListener();

        $this->registerMvcRenderingStrategies(events: $events);
        $this->registerViewStrategies();

        $routeNotFoundStrategy->attach($events);
        $exceptionStrategy->attach($events);
        $events->attach(eventName: MvcEvent::EVENT_DISPATCH_ERROR, listener: [$injectViewModelListener, 'injectViewModel'], priority: -100);
        $events->attach(eventName: MvcEvent::EVENT_RENDER_ERROR, listener: [$injectViewModelListener, 'injectViewModel'], priority: -100);
        $mvcRenderingStrategy->attach($events);

        $sharedEvents->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: [$createViewModelListener, 'createViewModelFromArray'],
            priority: -80
        );
        $sharedEvents->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: [$routeNotFoundStrategy, 'prepareNotFoundViewModel'],
            priority: -90
        );
        $sharedEvents->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: [$createViewModelListener, 'createViewModelFromNull'],
            priority: -80
        );
        $sharedEvents->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: [$injectTemplateListener, 'injectTemplate'],
            priority: -90
        );
        $sharedEvents->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: [$injectViewModelListener, 'injectViewModel'],
            priority: -100
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

        $this->view = $this->services->get(id: View::class);
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
        $layoutTemplate  = $this->services->get(id: 'HttpDefaultRenderingStrategy')->getLayoutTemplate();
        $model->setTemplate(template: $layoutTemplate);

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
        if (! isset($this->config['mvc_strategies'])) {
            return;
        }
        $mvcStrategies = $this->config['mvc_strategies'];
        if (is_string(value: $mvcStrategies)) {
            $mvcStrategies = [$mvcStrategies];
        }
        if (! is_array(value: $mvcStrategies) && ! $mvcStrategies instanceof Traversable) {
            return;
        }

        foreach ($mvcStrategies as $mvcStrategy) {
            if (! is_string(value: $mvcStrategy)) {
                continue;
            }

            $listener = $this->services->get(id: $mvcStrategy);
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
    protected function registerViewStrategies(): void
    {
        if (! isset($this->config['strategies'])) {
            return;
        }
        $strategies = $this->config['strategies'];
        if (is_string(value: $strategies)) {
            $strategies = [$strategies];
        }
        if (! is_array(value: $strategies) && ! $strategies instanceof Traversable) {
            return;
        }

        $view   = $this->getView();
        $events = $view->getEventManager();

        foreach ($strategies as $strategy) {
            if (! is_string(value: $strategy)) {
                continue;
            }

            $listener = $this->services->get(id: $strategy);
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
        $model   = $this->getViewModel();
        $plugins = $this->services->get(id: 'ViewHelperManager');
        $plugin  = $plugins->get('viewmodel');
        $plugin->setRoot($model);
    }
}
