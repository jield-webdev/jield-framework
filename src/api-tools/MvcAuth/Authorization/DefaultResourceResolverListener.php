<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authorization;

use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Laminas\Http\Request;
use Laminas\Router\RouteMatch;
use Laminas\Stdlib\RequestInterface;
use function array_key_exists;
use function sprintf;

class DefaultResourceResolverListener
{
    protected array $restControllers;

    public function __construct(array $restControllers = [])
    {
        $this->restControllers = $restControllers;
    }

    /**
     * Attempt to determine the authorization resource based on the request
     *
     * Looks at the matched controller.
     *
     * If the controller is in the list of rest controllers, determines if we
     * have a collection or a resource, based on the presence of the named
     * identifier in the route matches or query string.
     *
     * Otherwise, looks for the presence of an "action" parameter in the route
     * matches.
     *
     * Once created, it is injected into the $mvcAuthEvent.
     */
    public function __invoke(MvcAuthEvent $mvcAuthEvent): void
    {
        $mvcEvent   = $mvcAuthEvent->getMvcEvent();
        $request    = $mvcEvent->getRequest();
        $routeMatch = $mvcEvent->getRouteMatch();

        $resource = $this->buildResourceString(routeMatch: $routeMatch, request: $request);
        if (!$resource) {
            return;
        }

        $mvcAuthEvent->setResource(resource: $resource);
    }

    /**
     * Creates a resource string based on the controller service name and type
     *
     * For REST services (those passed to the constructor), it returns one of:
     *
     * - <controller service name>::entity
     * - <controller service name>::collection
     *
     * For all others, it uses the "action" route match parameter:
     *
     * - <controller service name>::<action>
     *
     * If it cannot resolve a controller service name, boolean false is returned.
     *
     * @param RouteMatch $routeMatch
     * @param RequestInterface $request
     * @return false|string
     */
    public function buildResourceString(RouteMatch $routeMatch, RequestInterface $request): false|string
    {
        // Considerations:
        // - We want the controller service name
        $controller = $routeMatch->getParam(name: 'controller', default: false);
        if (!$controller) {
            return false;
        }

        // - Is this an RPC or a REST call?
        //   - Basically, if it's not in the api-tools-rest configuration, we assume RPC
        if (!array_key_exists(key: $controller, array: $this->restControllers)) {
            $action = $routeMatch->getParam(name: 'action', default: 'index');
            return sprintf('%s::%s', $controller, $action);
        }

        //   - If it is a REST controller, we need to know if we have a
        //     resource or a controller. The way to determine that is if we have
        //     an identifier. We find that info from the route parameters.
        $identifierName = $this->restControllers[$controller];
        $id             = $this->getIdentifier(identifierName: $identifierName, routeMatch: $routeMatch, request: $request);
        if ($id !== false) {
            return sprintf('%s::entity', $controller);
        }

        return sprintf('%s::collection', $controller);
    }

    /**
     * Attempt to retrieve the identifier for a given request
     *
     * Checks first if the $identifierName is in the route matches, and then
     * as a query string parameter.
     *
     * @param string $identifierName
     * @param RouteMatch $routeMatch Validated by calling method.
     * @param RequestInterface $request
     * @return false|mixed
     */
    protected function getIdentifier(string $identifierName, RouteMatch $routeMatch, RequestInterface $request): mixed
    {
        $id = $routeMatch->getParam(name: $identifierName, default: false);
        if ($id !== false) {
            return $id;
        }

        if (!$request instanceof Request) {
            return false;
        }

        return $request->getQuery(name: $identifierName, default: false);
    }
}
