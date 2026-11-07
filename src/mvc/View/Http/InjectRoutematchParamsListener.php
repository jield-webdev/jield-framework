<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Http;

use Jield\Mvc\MvcEvent;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request as HttpRequest;
use Laminas\Stdlib\ParametersInterface;
use Webmozart\Assert\Assert;

class InjectRoutematchParamsListener extends AbstractListenerAggregate
{
    /**
     * Should request params overwrite existing request params?
     */
    protected bool $overwrite = true;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, $this->injectParams(...), 90);
    }

    /**
     * Take parameters from RouteMatch and inject them into the request.
     */
    public function injectParams(MvcEvent $e): void
    {
        $routeMatchParams = Assert::notNull($e->getRouteMatch())->getParams();
        $request = $e->getRequest();

        if (!$request instanceof HttpRequest) {
            // unsupported request type
            return;
        }

        $params = Assert::isInstanceOf($request->getQuery(), ParametersInterface::class);

        if ($this->overwrite) {
            // Overwrite existing parameters, or create new ones if not present.
            /** @mago-var mixed $val */
            foreach ($routeMatchParams as $key => $val) {
                $params->set($key, $val);
            }
            return;
        }

        // Only create new parameters.
        /** @mago-var mixed $val */
        foreach ($routeMatchParams as $key => $val) {
            if (array_key_exists($key, Assert::isArray($params->toArray()))) {
                continue;
            }

            $params->set($key, $val);
        }
    }

    /**
     * Should RouteMatch parameters replace existing Request params?
     *
     * @param  bool $overwrite
     */
    public function setOverwrite($overwrite): void
    {
        $this->overwrite = $overwrite;
    }

    public function getOverwrite(): bool
    {
        return $this->overwrite;
    }
}
