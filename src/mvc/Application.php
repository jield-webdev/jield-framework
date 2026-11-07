<?php

declare(strict_types=1);

namespace Jield\Mvc;

use Jield\ModuleManager\ModuleManager;
use Jield\Mvc\Service\ServiceManagerConfig;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Router\RouteStackInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\RequestInterface;
use Laminas\Stdlib\ResponseInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function array_merge;
use function array_unique;

/**
 * Main application class for invoking applications
 *
 * Expects the user will provide a configured ServiceManager, configured with
 * the following services:
 *
 * - EventManager
 * - ModuleManager
 * - Request
 * - Response
 * - RouteListener
 * - Router
 * - DispatchListener
 * - ViewManager
 *
 * The most common workflow is:
 * <code>
 * $services = new Laminas\ServiceManager\ServiceManager($servicesConfig);
 * $app      = new Application($appConfig, $services);
 * $app->bootstrap();
 * $response = $app->run();
 * $response->send();
 * </code>
 *
 * bootstrap() opts in to the default route, dispatch, and view listeners,
 * sets up the MvcEvent, and triggers the bootstrap event. This can be omitted
 * if you wish to setup your own listeners and/or workflow; alternately, you
 * can simply extend the class to override such behavior.
 */
class Application implements ApplicationInterface, EventManagerAwareInterface
{
    public const string ERROR_CONTROLLER_CANNOT_DISPATCH = 'error-controller-cannot-dispatch';
    public const string ERROR_CONTROLLER_NOT_FOUND = 'error-controller-not-found';
    public const string ERROR_CONTROLLER_INVALID = 'error-controller-invalid';
    public const string ERROR_EXCEPTION = 'error-exception';
    public const string ERROR_ROUTER_NO_MATCH = 'error-router-no-match';

    /**
     * Default application event listeners
     *
     * @var array
     */
    protected array $defaultListeners = [
        'RouteListener',
        'DispatchListener',
        'HttpMethodListener',
        'ViewManager',
        'SendResponseListener',
    ];

    protected MvcEvent $event;

    /**
     * Constructor
     */
    public function __construct(
        protected ContainerInterface $serviceManager,
        protected ?EventManagerInterface $events = null,
        protected ?RequestInterface $request = null,
        protected ?ResponseInterface $response = null,
    ) {
        $this->setEventManager(
            eventManager: $events ?: Service::get(
                $serviceManager,
                \Laminas\EventManager\EventManagerInterface::class,
                'EventManager',
            ),
        );
        $this->request = $request ?: Service::get($serviceManager, \Laminas\Stdlib\RequestInterface::class, 'Request');
        $this->response = $response ?: Service::get(
            $serviceManager,
            \Laminas\Stdlib\ResponseInterface::class,
            'Response',
        );
    }

    /**
     * Retrieve the application configuration
     *
     * @return array|object
     */
    public function getConfig(): object|array
    {
        return $this->serviceManager->get('config');
    }

    /**
     * Bootstrap the application
     *
     * Defines and binds the MvcEvent, and passes it the request, response, and
     * router. Attaches the ViewManager as a listener. Triggers the bootstrap
     * event.
     *
     * @param array $listeners List of listeners to attach.
     * @return static
     */
    public function bootstrap(array $listeners = []): static
    {
        $serviceManager = $this->serviceManager;
        $events = $this->events;

        // Setup default listeners
        $listeners = array_unique(array: array_merge($this->defaultListeners, $listeners));

        foreach ($listeners as $listener) {
            Value::call($serviceManager->get($listener), 'attach', [$events]);
        }

        // Setup MVC Event
        $this->event = $event = new MvcEvent();
        $event->setName(name: MvcEvent::EVENT_BOOTSTRAP);
        $event->setTarget(target: $this);
        $event->setApplication(application: $this);
        $event->setRequest(request: Assert::isInstanceOf($this->request, \Laminas\Stdlib\RequestInterface::class));
        $event->setResponse(response: Assert::isInstanceOf($this->response, \Laminas\Stdlib\ResponseInterface::class));
        $event->setRouter(router: Service::get($serviceManager, RouteStackInterface::class, 'Router'));

        // Trigger bootstrap events
        Assert::notNull($events)->triggerEvent(event: $event);

        return $this;
    }

    /**
     * Retrieve the service manager
     */
    public function getServiceManager(): ContainerInterface
    {
        return $this->serviceManager;
    }

    /**
     * Get the request object
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return Assert::isInstanceOf($this->request, \Laminas\Stdlib\RequestInterface::class);
    }

    /**
     * Get the response object
     *
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return Assert::isInstanceOf($this->response, \Laminas\Stdlib\ResponseInterface::class);
    }

    /**
     * Get the MVC event instance
     *
     * @return MvcEvent
     */
    public function getMvcEvent(): MvcEvent
    {
        return $this->event;
    }

    /**
     * Set the event manager instance
     *
     * @return void
     */
    public function setEventManager(EventManagerInterface $eventManager): void
    {
        $eventManager->setIdentifiers(identifiers: [
            self::class,
            static::class,
        ]);
        $this->events = $eventManager;
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    public function getEventManager(): EventManagerInterface
    {
        return Assert::isInstanceOf($this->events, \Laminas\EventManager\EventManagerInterface::class);
    }

    /**
     * Static method for quick and easy initialization of the Application.
     *
     * If you use this init() method, you cannot specify a service with the
     * name of 'ApplicationConfig' in your service manager config. This name is
     * reserved to hold the array from application.config.php.
     *
     * The following services can only be overridden from application.config.php:
     *
     * - ModuleManager
     * - SharedEventManager
     * - EventManager & Laminas\EventManager\EventManagerInterface
     *
     * All other services are configured after module loading, thus can be
     * overridden by modules.
     *
     * @param array $configuration
     * @return Application
     */
    public static function init($configuration = []): Application
    {
        // Prepare the service manager
        $smConfig = $configuration['service_manager'] ?? [];
        $smConfig = new ServiceManagerConfig(config: $smConfig);

        $serviceManager = new ServiceManager();
        $smConfig->configureServiceManager(services: $serviceManager);
        $serviceManager->setService(name: 'ApplicationConfig', service: $configuration);

        // Load modules
        Service::get($serviceManager, ModuleManager::class, 'ModuleManager')->loadModules();

        // Prepare list of listeners to bootstrap
        $listenersFromAppConfig = $configuration['listeners'] ?? [];
        $config = $serviceManager->get('config');
        $listenersFromConfigService = $config['listeners'] ?? [];

        $listeners = array_unique(array: array_merge(
            $listenersFromConfigService,
            Assert::isArray($listenersFromAppConfig),
        ));

        return Assert::isInstanceOf(
            Service::get($serviceManager, \Jield\Mvc\ApplicationInterface::class, 'Application')->bootstrap($listeners),
            \Jield\Mvc\Application::class,
        );
    }

    /**
     * Run the application
     *
     * @triggers route(MvcEvent)
     *           Routes the request, and sets the RouteMatch object in the event.
     * @triggers dispatch(MvcEvent)
     *           Dispatches a request, using the discovered RouteMatch and
     *           provided request.
     * @triggers dispatch.error(MvcEvent)
     *           On errors (controller not found, action not supported, etc.),
     *           populates the event with information about the error type,
     *           discovered controller, and controller class (if known).
     *           Typically, a handler should return a populated Response object
     *           that can be returned immediately.
     * @return static
     */
    public function run(): static
    {
        $events = $this->events;
        $event = $this->event;

        // Define callback used to determine whether or not to short-circuit
        $shortCircuit = static function ($r) use ($event): bool {
            if ($r instanceof ResponseInterface) {
                return true;
            }
            if ($event->getError()) {
                return true;
            }
            return false;
        };

        // Trigger route event
        $event->setName(name: MvcEvent::EVENT_ROUTE);
        $event->stopPropagation(flag: false); // Clear before triggering
        $result = Assert::notNull($events)->triggerEventUntil(callback: $shortCircuit, event: $event);
        if ($result->stopped()) {
            $response = $result->last();
            if ($response instanceof ResponseInterface) {
                $event->setName(name: MvcEvent::EVENT_FINISH);
                $event->setTarget(target: $this);
                $event->setResponse(response: $response);
                $event->stopPropagation(flag: false); // Clear before triggering
                Assert::notNull($events)->triggerEvent(event: $event);
                $this->response = $response;
                return $this;
            }
        }

        if ($event->getError()) {
            return $this->completeRequest(event: $event);
        }

        // Trigger dispatch event
        $event->setName(name: MvcEvent::EVENT_DISPATCH);
        $event->stopPropagation(flag: false); // Clear before triggering
        $result = Assert::notNull($events)->triggerEventUntil(callback: $shortCircuit, event: $event);

        // Complete response
        $response = $result->last();
        if ($response instanceof ResponseInterface) {
            $event->setName(name: MvcEvent::EVENT_FINISH);
            $event->setTarget(target: $this);
            $event->setResponse(response: $response);
            $event->stopPropagation(flag: false); // Clear before triggering
            Assert::notNull($events)->triggerEvent(event: $event);
            $this->response = $response;
            return $this;
        }

        $response = $this->response;
        $event->setResponse(response: Assert::isInstanceOf($response, \Laminas\Stdlib\ResponseInterface::class));
        return $this->completeRequest(event: $event);
    }

    /**
     * Complete the request
     *
     * Triggers "render" and "finish" events, and returns response from
     * event object.
     *
     * @return static
     */
    protected function completeRequest(MvcEvent $event): static
    {
        $events = $this->events;
        $event->setTarget(target: $this);

        $event->setName(name: MvcEvent::EVENT_RENDER);
        $event->stopPropagation(flag: false); // Clear before triggering
        Assert::notNull($events)->triggerEvent(event: $event);

        $event->setName(name: MvcEvent::EVENT_FINISH);
        $event->stopPropagation(flag: false); // Clear before triggering
        Assert::notNull($events)->triggerEvent(event: $event);

        return $this;
    }
}
