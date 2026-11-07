<?php

declare(strict_types=1);

namespace Jield\Mvc;

use ArrayObject;
use Exception;
use Jield\Mvc\Controller\ControllerManager;
use Jield\Mvc\Router\RouteMatch;
use Jield\Stdlib\Value;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\Stdlib\ArrayUtils;
use Throwable;
use Webmozart\Assert\Assert;

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
    public function __construct(
        private readonly ControllerManager $controllerManager,
    ) {}

    /**
     * Attach listeners to an event manager
     *
     * @param int $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH, listener: $this->onDispatch(...));
    }

    /**
     * Listen to the "dispatch" event
     *
     * @return mixed
     */
    public function onDispatch(MvcEvent $e): mixed
    {
        if (null !== $e->getResult()) {
            return null;
        }

        $routeMatch = $e->getRouteMatch();
        /** @mago-var mixed $controllerName */
        $controllerName = $routeMatch instanceof RouteMatch
            ? $routeMatch->getParam(name: 'controller', default: 'not-found')
            : 'not-found';
        $application = $e->getApplication();
        $controllerManager = $this->controllerManager;

        // Query abstract controllers, too!
        if (!$controllerManager->has(Assert::string($controllerName))) {
            /** @mago-var mixed $return */
            $return = $this->marshalControllerNotFoundEvent(
                type: Application::ERROR_CONTROLLER_NOT_FOUND,
                controllerName: Assert::string($controllerName),
                event: $e,
                application: Assert::isInstanceOf($application, \Jield\Mvc\Application::class),
            );
            return $this->complete(return: $return, event: $e);
        }

        try {
            /** @mago-var mixed $controller */
            $controller = $controllerManager->get(Assert::string($controllerName));
        } catch (InvalidServiceException $exception) {
            /** @mago-var mixed $return */
            $return = $this->marshalControllerNotFoundEvent(
                type: Application::ERROR_CONTROLLER_INVALID,
                controllerName: Assert::string($controllerName),
                event: $e,
                application: Assert::isInstanceOf($application, \Jield\Mvc\Application::class),
                exception: $exception,
            );
            return $this->complete(return: $return, event: $e);
        } catch (Throwable $exception) {
            /** @mago-var mixed $return */
            $return = $this->marshalBadControllerEvent(
                controllerName: Assert::string($controllerName),
                event: $e,
                application: Assert::isInstanceOf($application, \Jield\Mvc\Application::class),
                exception: $exception,
            );
            return $this->complete(return: $return, event: $e);
        }

        if ($controller instanceof InjectApplicationEventInterface) {
            $controller->setEvent($e);
        }

        $request = $e->getRequest();
        $response = Assert::notNull($application)->getResponse();
        $caughtException = null;
        $return = null;

        try {
            /** @mago-var mixed $return */
            $return = Value::call($controller, 'dispatch', [
                'request' => $request,
                'response' => $response,
            ]);
        } catch (Throwable $ex) {
            $caughtException = $ex;
        }

        if ($caughtException !== null) {
            $e->setName(MvcEvent::EVENT_DISPATCH_ERROR);
            $e->setError(Application::ERROR_EXCEPTION);
            $e->setController(Assert::string($controllerName));
            $e->setControllerClass(Assert::string($controller::class));
            $e->setParam(name: 'exception', value: $caughtException);

            /** @mago-var mixed $return */
            $return = Assert::notNull($application)->getEventManager()->triggerEvent($e)->last();
            if (!$return) {
                /** @mago-var mixed $return */
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
        if (!is_object($return) && ArrayUtils::hasStringKeys($return)) {
            $return = new ArrayObject(Assert::isArray($return), flags: ArrayObject::ARRAY_AS_PROPS);
        }
        $event->setResult($return);
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
        string $type,
        string $controllerName,
        MvcEvent $event,
        Application $application,
        Throwable|Exception|null $exception = null,
    ): mixed {
        $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError($type);
        $event->setController($controllerName);
        $event->setControllerClass('invalid controller class or alias: ' . $controllerName);
        if ($exception !== null) {
            $event->setParam(name: 'exception', value: $exception);
        }

        $events = $application->getEventManager();
        $results = $events->triggerEvent($event);
        /** @mago-var mixed $return */
        $return = $results->last();
        if (!$return) {
            /** @mago-var mixed $return */
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
        string $controllerName,
        MvcEvent $event,
        Application $application,
        Throwable|Exception $exception,
    ): mixed {
        $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError(Application::ERROR_EXCEPTION);
        $event->setController($controllerName);
        $event->setParam(name: 'exception', value: $exception);

        $events = $application->getEventManager();
        $results = $events->triggerEvent($event);
        /** @mago-var mixed $return */
        $return = $results->last();
        if (!$return) {
            return $event->getResult();
        }

        return $return;
    }
}
