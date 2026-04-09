<?php

namespace Jield\Mvc\Controller\Plugin;

use Laminas\EventManager\EventInterface;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\Exception\InvalidArgumentException;
use Jield\Mvc\Exception\RuntimeException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\ModuleRouteListener;
use Jield\Mvc\MvcEvent;
use Laminas\Router\RouteStackInterface;
use Traversable;

use function array_merge;
use function func_num_args;
use function is_array;
use function is_bool;
use function iterator_to_array;

class Url extends AbstractPlugin
{
    /**
     * Generates a URL based on a route
     *
     * @param string|null $route              RouteInterface name
     * @param Traversable|array $params             Parameters to use in url generation, if any
     * @param bool|array $options            RouteInterface-specific options to use in url generation, if any.
     *                                                If boolean, and no fourth argument, used as $reuseMatchedParams.
     * @param bool $reuseMatchedParams Whether to reuse matched parameters
     * @return string
     *@throws InvalidArgumentException
     * @throws DomainException
     * @throws \RuntimeException
     */
    public function fromRoute(?string $route = null, Traversable|array $params = [], bool|array $options = [], bool $reuseMatchedParams = false): string
    {
        $controller = $this->getController();
        if (! $controller instanceof InjectApplicationEventInterface) {
            throw new DomainException(
                message: 'Url plugin requires a controller that implements InjectApplicationEventInterface'
            );
        }

        if (! is_array(value: $params)) {
            if (! $params instanceof Traversable) {
                throw new InvalidArgumentException(
                    message: 'Params is expected to be an array or a Traversable object'
                );
            }
            $params = iterator_to_array(iterator: $params);
        }

        $event   = $controller->getEvent();
        $router  = null;
        $matches = null;
        if ($event instanceof MvcEvent) {
            $router  = $event->getRouter();
            $matches = $event->getRouteMatch();
        } elseif ($event instanceof EventInterface) {
            $router  = $event->getParam(name: 'router', default: false);
            $matches = $event->getParam(name: 'route-match', default: false);
        }
        if (! $router instanceof RouteStackInterface) {
            throw new DomainException(
                message: 'Url plugin requires that controller event compose a router; none found'
            );
        }

        if (3 === func_num_args() && is_bool(value: $options)) {
            $reuseMatchedParams = $options;
            $options            = [];
        }

        if ($route === null) {
            if (! $matches) {
                throw new RuntimeException(message: 'No RouteMatch instance present');
            }

            $route = $matches->getMatchedRouteName();

            if ($route === null) {
                throw new RuntimeException(message: 'RouteMatch does not contain a matched route name');
            }
        }

        if ($reuseMatchedParams && $matches) {
            $routeMatchParams = $matches->getParams();

            if (isset($routeMatchParams[ModuleRouteListener::ORIGINAL_CONTROLLER])) {
                $routeMatchParams['controller'] = $routeMatchParams[ModuleRouteListener::ORIGINAL_CONTROLLER];
                unset($routeMatchParams[ModuleRouteListener::ORIGINAL_CONTROLLER]);
            }

            if (isset($routeMatchParams[ModuleRouteListener::MODULE_NAMESPACE])) {
                unset($routeMatchParams[ModuleRouteListener::MODULE_NAMESPACE]);
            }

            $params = array_merge($routeMatchParams, $params);
        }

        $options['name'] = $route;
        return $router->assemble(params: $params, options: $options);
    }
}
