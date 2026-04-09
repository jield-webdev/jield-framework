<?php

declare(strict_types=1);

namespace Jield\Authorize\Guard;

use Jield\Authorize\Exception\UnAuthorizedException;
use Jield\Authorize\Service\AuthorizeService;
use Laminas\EventManager\EventManagerInterface;
use Jield\Http\Request as HttpRequest;
use Jield\Mvc\ApplicationInterface;
use Jield\Mvc\MvcEvent;
use function sprintf;
use function strtolower;

/**
 * Controller Guard listener, allows checking of permissions
 * during {@see \Jield\Mvc\MvcEvent::EVENT_DISPATCH}
 */
class Controller extends AbstractGuard
{
    public const string ERROR = 'error-unauthorized-controller';

    protected function extractResourcesFromRule(array $rule): array
    {
        $results        = [];
        $rule['action'] = isset($rule['action']) ? (array)$rule['action'] : [null];

        foreach ((array)$rule['controller'] as $controller) {
            foreach ($rule['action'] as $action) {
                $results[] = $this->getResourceName(controller: $controller, action: $action);
            }
        }

        return $results;
    }

    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_ROUTE, listener: $this->onDispatch(...), priority: -1000);
    }

    public function getResourceName(string $controller, ?string $action = null): string
    {
        if (isset($action)) {
            return sprintf('controller/%s:%s', $controller, strtolower(string: $action));
        }

        return sprintf('controller/%s', $controller);
    }

    public function onDispatch(MvcEvent $event): mixed
    {
        /** @var Authorize $service */
        $service    = $this->container->get(Authorize::class);
        $match      = $event->getRouteMatch();
        $controller = $match->getParam(name: 'controller');
        $action     = $match->getParam(name: 'action');
        $request    = $event->getRequest();
        $method     = $request instanceof HttpRequest ? strtolower(string: (string)$request->getMethod()) : null;

        $authorized = PHP_SAPI === 'cli'
            || $service->isAllowed(resource: $this->getResourceName(controller: $controller))
            || $service->isAllowed(resource: $this->getResourceName(controller: $controller, action: $action))
            || ($method && $service->isAllowed(resource: $this->getResourceName(controller: $controller, action: $method)));

        if ($authorized) {
            return $request;
        }

        $event->setError(message: static::ERROR);
        $event->setParam(name: 'identity', value: $service->getIdentity());
        $event->setParam(name: 'controller', value: $controller);
        $event->setParam(name: 'action', value: $action);

        $errorMessage = sprintf("You are not authorized to access %s:%s", $controller, $action);
        $event->setParam(name: 'exception', value: new UnAuthorizedException(message: $errorMessage));

        /** @var ApplicationInterface $app */
        $app          = $event->getTarget();
        $eventManager = $app->getEventManager();

        $event->setName(name: MvcEvent::EVENT_DISPATCH_ERROR);
        $results = $eventManager->triggerEvent(event: $event);

        $return = $results->last();
        if (!$return) {
            return $event->getResult();
        }

        return $return;
    }
}
