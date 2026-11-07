<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin;

use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\Exception\InvalidArgumentException;
use Jield\Mvc\Exception\RuntimeException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\ModuleRouteListener;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Value;
use Laminas\Router\RouteStackInterface;
use Traversable;
use Webmozart\Assert\Assert;

use function array_merge;
use function func_num_args;
use function is_array;
use function is_bool;
use function iterator_to_array;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
class Url extends AbstractPlugin
{
    /**
     * Generates a URL based on a route
     *
     * @param  string             $route              RouteInterface name
     * @param  mixed              $params             Parameters to use in url generation, if any
     * @param  array|bool         $options            RouteInterface-specific options to use in url generation, if any.
     *                                                If boolean, and no fourth argument, used as $reuseMatchedParams.
     * @param  bool               $reuseMatchedParams Whether to reuse matched parameters
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws DomainException
     * @throws \ReflectionException
     */
    public function fromRoute($route = null, $params = [], $options = [], $reuseMatchedParams = false): string
    {
        $controller = $this->getController();
        if (!$controller instanceof InjectApplicationEventInterface) {
            throw new DomainException(
                'Url plugin requires a controller that implements InjectApplicationEventInterface',
            );
        }

        if (!is_array($params)) {
            if (!$params instanceof Traversable) {
                throw new InvalidArgumentException('Params is expected to be an array or a Traversable object');
            }
            $params = iterator_to_array($params);
        }

        $event = $controller->getEvent();
        $router = null;
        $matches = null;
        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(1)
        if ($event instanceof MvcEvent) {
            $router = $event->getRouter();
            $matches = $event->getRouteMatch();
        } else {
            /** @mago-var mixed $router */
            $router = $event->getParam('router', false);
            /** @mago-var mixed $matches */
            $matches = $event->getParam('route-match', false);
        }
        if (!$router instanceof RouteStackInterface) {
            throw new DomainException('Url plugin requires that controller event compose a router; none found');
        }

        if (3 === func_num_args() && is_bool($options)) {
            $reuseMatchedParams = $options;
            $options = [];
        }

        if ($route === null) {
            if (!$matches) {
                throw new RuntimeException('No RouteMatch instance present');
            }

            /** @mago-var mixed $route */
            $route = Value::call($matches, 'getMatchedRouteName');

            if ($route === null) {
                throw new RuntimeException('RouteMatch does not contain a matched route name');
            }
        }

        if ($reuseMatchedParams && Value::truthy($matches)) {
            $routeMatchParams = Assert::isArray(Value::call($matches, 'getParams'));

            if (($routeMatchParams[ModuleRouteListener::ORIGINAL_CONTROLLER] ?? null) !== null) {
                $routeMatchParams['controller'] = $routeMatchParams[ModuleRouteListener::ORIGINAL_CONTROLLER];
                unset($routeMatchParams[ModuleRouteListener::ORIGINAL_CONTROLLER]);
            }

            if (($routeMatchParams[ModuleRouteListener::MODULE_NAMESPACE] ?? null) !== null) {
                unset($routeMatchParams[ModuleRouteListener::MODULE_NAMESPACE]);
            }

            $params = array_merge(Assert::isArray($routeMatchParams), $params);
        }

        $options = Assert::isArray($options);
        $options['name'] = $route;
        return $router->assemble(Value::routeParams($params), $options)->toString();
    }
}
