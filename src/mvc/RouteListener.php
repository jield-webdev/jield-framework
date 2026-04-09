<?php

namespace Jield\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Router\RouteMatch;

class RouteListener extends AbstractListenerAggregate
{
    /**
     * Attach to an event manager
     *
     * @param int $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_ROUTE, listener: [$this, 'onRoute']);
    }

    /**
     * Listen to the "route" event and attempt to route the request
     *
     * If no matches are returned, triggers "dispatch.error" in order to
     * create a 404 response.
     *
     * Seeds the event with the route match on completion.
     *
     * @return null|RouteMatch
     */
    public function onRoute(MvcEvent $event): ?RouteMatch
    {
        $request    = $event->getRequest();
        $router     = $event->getRouter();
        $routeMatch = $router->match(request: $request);

        if ($routeMatch instanceof RouteMatch) {
            $event->setRouteMatch(matches: $routeMatch);
            return $routeMatch;
        }

        $event->setName(name: MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError(message: Application::ERROR_ROUTER_NO_MATCH);

        $target  = $event->getTarget();
        $results = $target->getEventManager()->triggerEvent($event);
        if (! empty($results)) {
            return $results->last();
        }

        return $event->getParams();
    }
}
