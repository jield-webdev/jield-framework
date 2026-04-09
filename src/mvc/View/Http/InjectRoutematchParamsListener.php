<?php

namespace Jield\Mvc\View\Http;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Jield\Http\Request as HttpRequest;
use Jield\Mvc\MvcEvent;

class InjectRoutematchParamsListener extends AbstractListenerAggregate
{
    /**
     * Should request params overwrite existing request params?
     *
     * @var bool
     */
    protected bool $overwrite = true;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH, listener: [$this, 'injectParams'], priority: 90);
    }

    /**
     * Take parameters from RouteMatch and inject them into the request.
     *
     * @return void
     */
    public function injectParams(MvcEvent $e): void
    {
        $routeMatchParams = $e->getRouteMatch()->getParams();
        $request          = $e->getRequest();

        if (! $request instanceof HttpRequest) {
            // unsupported request type
            return;
        }

        $params = $request->get();

        if ($this->overwrite) {
            // Overwrite existing parameters, or create new ones if not present.
            foreach ($routeMatchParams as $key => $val) {
                $params->$key = $val;
            }
            return;
        }

        // Only create new parameters.
        foreach ($routeMatchParams as $key => $val) {
            if (! $params->offsetExists($key)) {
                $params->$key = $val;
            }
        }
    }

    /**
     * Should RouteMatch parameters replace existing Request params?
     *
     * @param bool $overwrite
     */
    public function setOverwrite(bool $overwrite): void
    {
        $this->overwrite = $overwrite;
    }

    /**
     * @return bool
     */
    public function getOverwrite(): bool
    {
        return $this->overwrite;
    }
}
