<?php

declare(strict_types=1);

namespace Jield\Mvc;

use Jield\Mvc\Router\RouteMatch;
use Jield\Stdlib\Value;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\AbstractMessage;
use Laminas\Http\Request;
use Nyholm\Psr7\ServerRequest;

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
        $request = Value::instance($event->getRequest(), \Laminas\Http\PhpEnvironment\Request::class);
        $router = $event->getRouter();
        $routingRequest = new ServerRequest(
            Value::string(
                Value::instance($request, Request::class)->getMethod(),
            ),
            Value::instance($request, Request::class)->getUriString(),
            Value::array(
                Value::instance($request, AbstractMessage::class)
                    ->getHeaders()
                    ->toArray(),
            ),
            $request->getContent(),
            Value::string($request->getVersion()),
            Value::array(Value::call($request->getServer(), 'toArray')),
        );
        $matched = $router->match($routingRequest);
        $routeMatch = $matched === null ? null : new RouteMatch($matched->getParams());
        if ($routeMatch !== null) {
            $routeMatch->setMatchedRouteName(Value::present($matched)->getMatchedRouteName());
        }

        if ($routeMatch instanceof RouteMatch) {
            $event->setRouteMatch($routeMatch);
            return $routeMatch;
        }

        $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError(Application::ERROR_ROUTER_NO_MATCH);

        $target = $event->getTarget();
        $results = Value::call(
            Value::call(
                Value::present(Value::object($target)),
                'getEventManager',
            ),
            'triggerEvent',
            [$event],
        );
        if (!empty($results)) {
            return Value::call($results, 'last');
        }

        return $event->getParams();
    }
}
