<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller;

use Jield\Mvc\Controller\Plugin\Forward;
use Jield\Mvc\Controller\Plugin\Layout;
use Jield\Mvc\Controller\Plugin\Params;
use Jield\Mvc\Controller\Plugin\Redirect;
use Jield\Mvc\Controller\Plugin\Url;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\View\Model\ViewModel;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventInterface as Event;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Header\Accept\FieldValuePart\AbstractFieldValuePart;
use Laminas\Http\PhpEnvironment\Response as HttpResponse;
use Laminas\Http\Request as HttpRequest;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\DispatchableInterface as Dispatchable;
use Laminas\Stdlib\RequestInterface as Request;
use Laminas\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ModelInterface;

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
abstract class AbstractController implements Dispatchable, EventManagerAwareInterface, InjectApplicationEventInterface
{
    protected ?PluginManager $plugins = null;

    protected ?Request $request = null;

    protected ?Response $response = null;

    protected ?MvcEvent $event = null;

    protected ?EventManagerInterface $events = null;

    /** @var null|string|string[] */
    protected string|array|null $eventIdentifier = null;

    /**
     * Transform an "action" token into a method name
     *
     * @param string $action
     * @return string
     */
    public static function getMethodFromAction($action): string
    {
        $method = str_replace(['.', '-', '_'], ' ', $action);
        $method = ucwords($method);
        $method = str_replace(' ', '', $method);
        $method = lcfirst($method);
        return $method . 'Action';
    }

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
        $e->setName(MvcEvent::EVENT_DISPATCH);
        $e->setRequest($request);
        $e->setResponse($response);
        $e->setTarget($this);

        $result = $this->getEventManager()->triggerEventUntil(static fn($test): bool => $test instanceof Response, $e);

        if ($result->stopped()) {
            return $result->last();
        }

        return $e->getResult();
    }

    /**
     * Get the attached event
     *
     * Will create a new MvcEvent if none provided.
     *
     * @return MvcEvent
     */
    public function getEvent(): MvcEvent
    {
        if (!$this->event) {
            $this->setEvent(new MvcEvent());
        }

        return $this->event;
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
            $event = new MvcEvent();
            $event->setParams($eventParams);
            unset($eventParams);
        }
        $this->event = $event;
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
        if (!$this->events) {
            $this->setEventManager(new EventManager());
        }

        return $this->events;
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

        $rightmostNsPos = strrpos($className, '\\');
        if ($rightmostNsPos) {
            $identifiers[] = strstr($className, '\\', true); // top namespace
            $identifiers[] = substr($className, 0, $rightmostNsPos); // full namespace
        }

        $eventManager->setIdentifiers(Value::strings(array_merge(
            $identifiers,
            array_values(class_implements($className) ?: []),
            Value::toArray($this->eventIdentifier),
        )));

        $this->events = $eventManager;
        $this->attachDefaultListeners();
    }

    /**
     * Register the default events for this controller
     *
     * @return void
     */
    protected function attachDefaultListeners(): void
    {
        $events = $this->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH, $this->onDispatch(...));
    }

    /**
     * Get request object
     *
     * @return Request
     */
    public function getRequest(): HttpRequest|Request
    {
        if (!$this->request) {
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
        if (!$this->response) {
            $this->response = new HttpResponse();
        }

        return $this->response;
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
    public function __call($method, $params)
    {
        $plugin = $this->plugin($method);
        if (is_callable($plugin)) {
            return call_user_func_array($plugin, $params);
        }

        return $plugin;
    }

    /**
     * Get plugin instance
     *
     * @param string $name Name of plugin to return
     * @param null|array $options Options to pass to plugin constructor (if not already instantiated)
     * @return mixed
     */
    public function plugin($name, ?array $options = null): mixed
    {
        return $this->getPluginManager()->get($name, $options);
    }

    /**
     * Get plugin manager
     *
     * @return PluginManager
     */
    public function getPluginManager(): PluginManager
    {
        if (!$this->plugins) {
            $this->setPluginManager(new PluginManager(new ServiceManager()));
        }

        $this->plugins->setController($this);
        return Value::instance($this->plugins, \Jield\Mvc\Controller\PluginManager::class);
    }

    /**
     * Set plugin manager
     *
     * @return static
     */
    public function setPluginManager(PluginManager $plugins): static
    {
        $this->plugins = $plugins;
        $this->plugins->setController($this);

        return $this;
    }
}
