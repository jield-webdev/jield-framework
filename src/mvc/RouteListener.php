<?php

declare(strict_types=1);

namespace Jield\Mvc;

use Jield\Mvc\Router\RouteMatch;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;

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
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute']);
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
        $request = \Jield\Stdlib\Value::instance($event->getRequest(), \Laminas\Http\PhpEnvironment\Request::class);
        $router = $event->getRouter();
        $routingRequest = new \Nyholm\Psr7\ServerRequest(
            \Jield\Stdlib\Value::string(\Jield\Stdlib\Value::instance($request, \Laminas\Http\Request::class)->getMethod()),
            \Jield\Stdlib\Value::instance($request, \Laminas\Http\Request::class)->getUriString(),
            \Jield\Stdlib\Value::array(\Jield\Stdlib\Value::instance($request, \Laminas\Http\AbstractMessage::class)->getHeaders()->toArray()),
            $request->getContent(),
            \Jield\Stdlib\Value::string($request->getVersion()),
            \Jield\Stdlib\Value::array(\Jield\Stdlib\Value::call($request->getServer(), 'toArray')),
        );
        $matched = $router->match($routingRequest);
        $routeMatch = $matched === null ? null : new RouteMatch($matched->getParams());
        if ($routeMatch !== null) {
            $routeMatch->setMatchedRouteName(\Jield\Stdlib\Value::present($matched)->getMatchedRouteName());
        }

        if ($routeMatch instanceof RouteMatch) {
            $event->setRouteMatch($routeMatch);
            return $routeMatch;
        }

        $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError(Application::ERROR_ROUTER_NO_MATCH);

        $target = $event->getTarget();
        /** @var mixed $results */
        $results = \Jield\Stdlib\Value::call(\Jield\Stdlib\Value::call(\Jield\Stdlib\Value::present(\Jield\Stdlib\Value::object($target)), 'getEventManager'), 'triggerEvent', [$event]);
        if (!empty($results)) {
            return \Jield\Stdlib\Value::call($results, 'last');
        }

        return $event->getParams();
    }
}
