<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest\Listener;

use Jield\ApiTools\Rest\RestController;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Laminas\EventManager\SharedEventManagerInterface;
use Override;

use function method_exists;

class RestParametersListener implements ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    protected array $sharedListeners = [];

    /** @param int $priority */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_DISPATCH,
            listener: $this->onDispatch(...),
            priority: 100,
        );
    }

    public function attachShared(SharedEventManagerInterface $events): void
    {
        $listener = $this->onDispatch(...);
        $events->attach(RestController::class, MvcEvent::EVENT_DISPATCH, listener: $listener, priority: 100);

        $this->sharedListeners[] = $listener;
    }

    public function detachShared(SharedEventManagerInterface $events): void
    {
        foreach ($this->sharedListeners as $index => $listener) {
            $events->detach(
                listener: \Jield\Stdlib\Value::callback($listener),
                identifier: RestController::class,
                eventName: MvcEvent::EVENT_DISPATCH,
            );
            unset($this->sharedListeners[$index]);
        }
    }

    /**
     * Listen to the dispatch event
     */
    public function onDispatch(MvcEvent $e): void
    {
        $controller = $e->getTarget();
        if (!$controller instanceof RestController) {
            return;
        }

        $request = $e->getRequest();
        if (!method_exists(object_or_class: $request, method: 'getQuery')) {
            return;
        }

        /** @var mixed $query */
        $query = $request->getQuery();
        $matches = $e->getRouteMatch();
        $resource = $controller->getResource();
        $resource->setQueryParams(params: \Jield\Stdlib\Value::nullableInstance($query, \Laminas\Stdlib\Parameters::class));
        $resource->setRouteMatch(matches: $matches);
    }
}
