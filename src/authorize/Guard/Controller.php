<?php

declare(strict_types=1);

namespace Jield\Authorize\Guard;

use Jield\Authorize\Exception\UnAuthorizedException;
use Jield\Authorize\Service\AuthorizeService;
use Jield\Mvc\ApplicationInterface;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request as HttpRequest;

use function sprintf;
use function strtolower;

/**
 * Controller Guard listener, allows checking of permissions
 * during {@see \Jield\Mvc\MvcEvent::EVENT_DISPATCH}
 */
final class Controller extends AbstractGuard
{
    public const string ERROR = 'error-unauthorized-controller';

    protected function extractResourcesFromRule(array $rule): array
    {
        $results = [];
        $rule['action'] = isset($rule['action']) ? Value::toArray($rule['action']) : [null];

        foreach (Value::toArray($rule['controller']) as $controller) {
            /** @var mixed $action */
            foreach ($rule['action'] as $action) {
                $results[] = $this->getResourceName(
                    controller: Value::string($controller),
                    action: Value::nullableString($action),
                );
            }
        }

        return $results;
    }

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_ROUTE,
            listener: $this->onDispatch(...),
            priority: -1000,
        );
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
        $service = Service::get($this->container, AuthorizeService::class);
        $match = $event->getRouteMatch();
        /** @var mixed $controller */
        $controller = Value::present($match)->getParam(name: 'controller');
        /** @var mixed $action */
        $action = Value::present($match)->getParam(name: 'action');
        $request = $event->getRequest();
        $method = $request instanceof HttpRequest ? strtolower(string: $request->getMethod()) : null;

        $authorized =
            PHP_SAPI === 'cli'
            || $service->isAllowed(resource: $this->getResourceName(controller: Value::string(
                $controller,
            )))
            || $service->isAllowed(resource: $this->getResourceName(
                controller: Value::string($controller),
                action: Value::nullableString($action),
            ))
            || $method
            && $service->isAllowed(resource: $this->getResourceName(
                controller: Value::string($controller),
                action: $method,
            ));

        if ($authorized) {
            return $request;
        }

        $event->setError(message: static::ERROR);
        $event->setParam(name: 'identity', value: $service->getIdentity());
        $event->setParam(name: 'controller', value: $controller);
        $event->setParam(name: 'action', value: $action);

        $errorMessage = sprintf(
            'You are not authorized to access %s:%s',
            Value::scalar($controller),
            Value::scalar($action),
        );
        $event->setParam(name: 'exception', value: new UnAuthorizedException(message: $errorMessage));

        /** @var ApplicationInterface $app */
        $app = $event->getTarget();
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
