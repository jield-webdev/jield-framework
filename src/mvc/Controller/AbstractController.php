<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller;

use Laminas\EventManager\EventInterface as Event;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Jield\Http\Header\Accept\FieldValuePart\AbstractFieldValuePart;
use Jield\Http\PhpEnvironment\Response as HttpResponse;
use Jield\Http\Request as HttpRequest;
use Jield\Mvc\Controller\Plugin\Forward;
use Jield\Mvc\Controller\Plugin\Layout;
use Jield\Mvc\Controller\Plugin\Params;
use Jield\Mvc\Controller\Plugin\Redirect;
use Jield\Mvc\Controller\Plugin\Url;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use Jield\Stdlib\DispatchableInterface as Dispatchable;
use Jield\Stdlib\RequestInterface as Request;
use Jield\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use function array_merge;
use function array_values;
use function call_user_func_array;
use function class_implements;
use function is_callable;
use function lcfirst;
use function str_replace;
use function strrpos;
use function strstr;
use function substr;
use function ucwords;

/**
 * Abstract controller
 *
 * Convenience methods for pre-built plugins (@see __call):
 * @codingStandardsIgnoreStart
 * @method ModelInterface acceptableViewModelSelector(array $matchAgainst = null, bool $returnDefault = true, AbstractFieldValuePart $resultReference = null)
 * @codingStandardsIgnoreEnd
 * @method Forward forward()
 * @method Layout|ModelInterface layout(string $template = null)
 * @method Params|mixed params(string $param = null, mixed $default = null)
 * @method Redirect redirect()
 * @method Url url()
 * @method ViewModel createHttpNotFoundModel(Response $response)
 */
abstract class AbstractController implements
    Dispatchable,
    EventManagerAwareInterface,
    InjectApplicationEventInterface
{
    /** @var PluginManager */
    protected ?PluginManager $plugins = null;

    /** @var Request */
    protected ?Request $request = null;

    /** @var Response */
    protected ?Response $response = null;

    /** @var Event */
    protected ?Event $event = null;

    /** @var EventManagerInterface */
    protected ?EventManagerInterface $events = null;

    /** @var null|string|string[] */
    protected string|array|null $eventIdentifier = null;

    /**
     * Execute the request
     *
     * @return mixed
     */
    abstract public function onDispatch(MvcEvent $e): mixed;

    /**
     * Dispatch a request
     *
     * @events dispatch.pre, dispatch.post
     * @return Response|mixed
     */
    public function dispatch(Request $request, ?Response $response = null): mixed
    {
        $this->request = $request;
        if (!$response) {
            $response = new HttpResponse();
        }
        $this->response = $response;

        $e = $this->getEvent();
        $e->setName(name: MvcEvent::EVENT_DISPATCH);
        $e->setRequest(request: $request);
        $e->setResponse(response: $response);
        $e->setTarget(target: $this);

        $result = $this->getEventManager()->triggerEventUntil(callback: static fn($test): bool => $test instanceof Response, event: $e);

        if ($result->stopped()) {
            return $result->last();
        }

        return $e->getResult();
    }

    /**
     * Get request object
     *
     * @return Request
     */
    public function getRequest(): HttpRequest|Request
    {
        if ($this->request === null) {
            $this->request = new HttpRequest();
        }

        return $this->request;
    }

    /**
     * Get response object
     *
     * @return Response
     */
    public function getResponse(): HttpResponse|Response
    {
        if ($this->response === null) {
            $this->response = new HttpResponse();
        }

        return $this->response;
    }

    /**
     * Set the event manager instance used by this context
     *
     * @return void
     */
    public function setEventManager(EventManagerInterface $eventManager): void
    {
        $className = static::class;

        $identifiers = [
            self::class,
            $className,
        ];

        $rightmostNsPos = strrpos(haystack: $className, needle: '\\');
        if ($rightmostNsPos) {
            $identifiers[] = strstr(haystack: $className, needle: '\\', before_needle: true); // top namespace
            $identifiers[] = substr(string: $className, offset: 0, length: $rightmostNsPos); // full namespace
        }

        $eventManager->setIdentifiers(identifiers: array_merge(
            $identifiers,
            array_values(array: class_implements(object_or_class: $className)),
            (array)$this->eventIdentifier
        ));

        $this->events = $eventManager;
        $this->attachDefaultListeners();
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
        if ($this->events === null) {
            $this->setEventManager(eventManager: new EventManager());
        }

        return $this->events;
    }

    /**
     * Set an event to use during dispatch
     *
     * By default, will re-cast to MvcEvent if another event type is provided.
     *
     * @return void
     */
    public function setEvent(Event $event): void
    {
        if (!$event instanceof MvcEvent) {
            $eventParams = $event->getParams();
            $event       = new MvcEvent();
            $event->setParams(params: $eventParams);
            unset($eventParams);
        }
        $this->event = $event;
    }

    /**
     * Get the attached event
     *
     * Will create a new MvcEvent if none provided.
     *
     * @return MvcEvent
     */
    public function getEvent(): Event
    {
        if ($this->event === null) {
            $this->setEvent(event: new MvcEvent());
        }

        return $this->event;
    }

    /**
     * Get plugin manager
     *
     * @return PluginManager
     */
    public function getPluginManager(): PluginManager
    {
        if ($this->plugins === null) {
            $this->setPluginManager(plugins: new PluginManager(creationContext: new ServiceManager()));
        }

        $this->plugins->setController(controller: $this);
        return $this->plugins;
    }

    /**
     * Set plugin manager
     *
     * @return AbstractController
     */
    public function setPluginManager(PluginManager $plugins): static
    {
        $this->plugins = $plugins;
        $this->plugins->setController(controller: $this);

        return $this;
    }

    /**
     * Get plugin instance
     *
     * @param string $name Name of plugin to return
     * @param null|array $options Options to pass to plugin constructor (if not already instantiated)
     * @return mixed
     */
    public function plugin(string $name, ?array $options = null): mixed
    {
        if ($options !== null) {
            return $this->getPluginManager()->build(name: $name, options: $options);
        }

        return $this->getPluginManager()->get(id: $name);
    }

    /**
     * Method overloading: return/call plugins
     *
     * If the plugin is a functor, call it, passing the parameters provided.
     * Otherwise, return the plugin instance.
     *
     * @param string $method
     * @param array $params
     * @return mixed
     */
    public function __call(string $method, array $params)
    {
        $plugin = $this->plugin(name: $method);
        if (is_callable(value: $plugin)) {
            return call_user_func_array(callback: $plugin, args: $params);
        }

        return $plugin;
    }

    /**
     * Register the default events for this controller
     *
     * @return void
     */
    protected function attachDefaultListeners(): void
    {
        $events = $this->getEventManager();
        $events->attach(eventName: MvcEvent::EVENT_DISPATCH, listener: [$this, 'onDispatch']);
    }

    /**
     * Transform an "action" token into a method name
     *
     * @param string $action
     * @return string
     */
    public static function getMethodFromAction(string $action): string
    {
        $method = str_replace(search: ['.', '-', '_'], replace: ' ', subject: $action);
        $method = ucwords(string: $method);
        $method = str_replace(search: ' ', replace: '', subject: $method);
        $method = lcfirst(string: $method);
        return $method . 'Action';
    }
}
