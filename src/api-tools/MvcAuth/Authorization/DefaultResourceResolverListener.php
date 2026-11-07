<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authorization;

use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Jield\Mvc\Router\RouteMatch;
use Laminas\Http\Request;
use Laminas\Stdlib\RequestInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function sprintf;

class DefaultResourceResolverListener
{
    public function __construct(
        protected array $restControllers = [],
    ) {}

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
        $mvcEvent = $mvcAuthEvent->getMvcEvent();
        $request = $mvcEvent->getRequest();
        $routeMatch = $mvcEvent->getRouteMatch();

        $resource = $this->buildResourceString(
            routeMatch: Assert::isInstanceOf($routeMatch, \Jield\Mvc\Router\RouteMatch::class),
            request: $request,
        );
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
        /** @mago-var mixed $controller */
        $controller = $routeMatch->getParam(name: 'controller', default: false);
        if (!$controller) {
            return false;
        }

        // - Is this an RPC or a REST call?
        //   - Basically, if it's not in the api-tools-rest configuration, we assume RPC
        if (!array_key_exists(key: Assert::scalar($controller), array: $this->restControllers)) {
            /** @mago-var mixed $action */
            $action = $routeMatch->getParam(name: 'action', default: 'index');
            return sprintf('%s::%s', \Jield\Stdlib\Value::scalar($controller), \Jield\Stdlib\Value::scalar($action));
        }

        //   - If it is a REST controller, we need to know if we have a
        //     resource or a controller. The way to determine that is if we have
        //     an identifier. We find that info from the route parameters.
        /** @mago-var mixed $identifierName */
        $identifierName = $this->restControllers[$controller];
        /** @mago-var mixed $id */
        $id = $this->getIdentifier(identifierName: Assert::string($identifierName), routeMatch: $routeMatch, request: $request);
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
     * @return mixed
     */
    protected function getIdentifier(string $identifierName, RouteMatch $routeMatch, RequestInterface $request): mixed
    {
        /** @mago-var mixed $id */
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
