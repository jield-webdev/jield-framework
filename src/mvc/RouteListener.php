<?php

declare(strict_types=1);

namespace Jield\Mvc;

use Jield\Mvc\Router\RouteMatch;
use Jield\Stdlib\Value;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Nyholm\Psr7\ServerRequest;
use Webmozart\Assert\Assert;

class RouteListener extends AbstractListenerAggregate
{
    /**
     * Attach to an event manager
     *
     * @param  int $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, $this->onRoute(...));
    }

    /**
     * Listen to the "route" event and attempt to route the request
     *
     * If no matches are returned, triggers "dispatch.error" in order to
     * create a 404 response.
     *
     * Seeds the event with the route match on completion.
     *
     * @return mixed
     */
    public function onRoute(MvcEvent $event): mixed
    {
        $request = Assert::isInstanceOf($event->getRequest(), \Laminas\Http\PhpEnvironment\Request::class);
        $router = $event->getRouter();
        $routingRequest = new ServerRequest(
            $request->getMethod(),
            $request->getUriString(),
            $request->getHeaders()->toArray(),
            $request->getContent(),
            $request->getVersion(),
            Assert::isArray(Value::call($request->getServer(), 'toArray')),
        );
        $matched = $router->match($routingRequest);
        $routeMatch = null;
        if ($matched !== null) {
            $routeMatch = new RouteMatch($matched->getParams());
            $routeMatch->setMatchedRouteName($matched->getMatchedRouteName());
        }

        if ($routeMatch instanceof RouteMatch) {
            $event->setRouteMatch($routeMatch);
            return $routeMatch;
        }

        $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError(Application::ERROR_ROUTER_NO_MATCH);

        $target = $event->getTarget();
        /** @mago-var mixed $results */
        $results = Value::call(Value::call(Assert::object($target), 'getEventManager'), 'triggerEvent', [$event]);
        if (!empty($results)) {
            return Value::call($results, 'last');
        }

        return $event->getParams();
    }
}
