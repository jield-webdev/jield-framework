<?php

namespace Jield\Mvc;

use ArrayObject;
use Exception;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Jield\Mvc\Controller\ControllerManager;
use Jield\Mvc\Exception\InvalidControllerException;
use Jield\Stdlib\ArrayUtils;
use Laminas\Router\RouteMatch;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Throwable;
use function is_object;

/**
 * Default dispatch listener
 *
 * Pulls controllers from the service manager's "ControllerManager" service.
 *
 * If the controller cannot be found a "404" result is set up. Otherwise it
 * will continue to try to load the controller.
 *
 * If the controller is not dispatchable it sets up a "404" result. In case
 * of any other exceptions it trigger the "dispatch.error" event in an attempt
 * to return a 500 status.
 *
 * If the controller subscribes to InjectApplicationEventInterface, it injects
 * the current MvcEvent into the controller.
 *
 * It then calls the controller's "dispatch" method, passing it the request and
 * response. If an exception occurs, it triggers the "dispatch.error" event,
 * in an attempt to return a 500 status.
 *
 * The return value of dispatching the controller is placed into the result
 * property of the MvcEvent, and returned.
 */
class DispatchListener extends AbstractListenerAggregate
{
    public function __construct(private readonly ControllerManager $controllerManager)
    {
    }

    /**
     * Attach listeners to an event manager
     *
     * @param int $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH, listener: [$this, 'onDispatch']);
    }

    /**
     * Listen to the "dispatch" event
     *
     * @return mixed
     */
    public function onDispatch(MvcEvent $e): mixed
    {
        if (null !== $e->getResult()) {
            return;
        }

        $routeMatch        = $e->getRouteMatch();
        $controllerName    = $routeMatch instanceof RouteMatch
            ? $routeMatch->getParam(name: 'controller', default: 'not-found')
            : 'not-found';
        $application       = $e->getApplication();
        $controllerManager = $this->controllerManager;

        // Query abstract controllers, too!
        if (!$controllerManager->has(id: $controllerName)) {
            $return = $this->marshalControllerNotFoundEvent(
                type: $application::ERROR_CONTROLLER_NOT_FOUND,
                controllerName: $controllerName,
                event: $e,
                application: $application
            );
            return $this->complete(return: $return, event: $e);
        }

        try {
            $controller = $controllerManager->get(id: $controllerName);
        } catch (InvalidServiceException $exception) {
            $return = $this->marshalControllerNotFoundEvent(
                type: $application::ERROR_CONTROLLER_INVALID,
                controllerName: $controllerName,
                event: $e,
                application: $application,
                exception: $exception
            );
            return $this->complete(return: $return, event: $e);
        } catch (Throwable $exception) {
            $return = $this->marshalBadControllerEvent(controllerName: $controllerName, event: $e, application: $application, exception: $exception);
            return $this->complete(return: $return, event: $e);
        }

        if ($controller instanceof InjectApplicationEventInterface) {
            $controller->setEvent(event: $e);
        }

        $request         = $e->getRequest();
        $response        = $application->getResponse();
        $caughtException = null;

        try {
            $return = $controller->dispatch(request: $request, response: $response);
        } catch (Throwable $ex) {
            $caughtException = $ex;
        }

        if ($caughtException !== null) {
            $e->setName(name: MvcEvent::EVENT_DISPATCH_ERROR);
            $e->setError(message: $application::ERROR_EXCEPTION);
            $e->setController(name: $controllerName);
            $e->setControllerClass(class: $controller::class);
            $e->setParam(name: 'exception', value: $caughtException);

            $return = $application->getEventManager()->triggerEvent(event: $e)->last();
            if (!$return) {
                $return = $e->getResult();
            }
        }

        return $this->complete(return: $return, event: $e);
    }

    /**
     * Complete the dispatch
     *
     * @return mixed
     */
    protected function complete(mixed $return, MvcEvent $event): mixed
    {
        if (!is_object(value: $return) && ArrayUtils::hasStringKeys(value: $return)) {
            $return = new ArrayObject(array: $return, flags: ArrayObject::ARRAY_AS_PROPS);
        }
        $event->setResult(result: $return);
        return $return;
    }

    /**
     * Marshal a controller not found exception event
     *
     * @param string $type
     * @param string $controllerName
     * @param Throwable|Exception|null $exception
     * @return mixed
     */
    protected function marshalControllerNotFoundEvent(
        string                   $type,
        string                   $controllerName,
        MvcEvent                 $event,
        Application              $application,
        Throwable|Exception|null $exception = null
    ): mixed
    {
        $event->setName(name: MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError(message: $type);
        $event->setController(name: $controllerName);
        $event->setControllerClass(class: 'invalid controller class or alias: ' . $controllerName);
        if ($exception !== null) {
            $event->setParam(name: 'exception', value: $exception);
        }

        $events  = $application->getEventManager();
        $results = $events->triggerEvent(event: $event);
        $return  = $results->last();
        if (!$return) {
            $return = $event->getResult();
        }
        return $return;
    }

    /**
     * Marshal a bad controller exception event
     *
     * @param string $controllerName
     * @param Throwable|Exception $exception
     * @return mixed
     */
    protected function marshalBadControllerEvent(
        string              $controllerName,
        MvcEvent            $event,
        Application         $application,
        Throwable|Exception $exception
    ): mixed
    {
        $event->setName(name: MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError(message: $application::ERROR_EXCEPTION);
        $event->setController(name: $controllerName);
        $event->setParam(name: 'exception', value: $exception);

        $events  = $application->getEventManager();
        $results = $events->triggerEvent(event: $event);
        $return  = $results->last();
        if (!$return) {
            return $event->getResult();
        }

        return $return;
    }
}
