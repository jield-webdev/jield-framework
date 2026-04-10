<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authorization;

use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Router\RouteMatch;

class DefaultAuthorizationListener
{
    /** @var AuthorizationInterface */
    protected AuthorizationInterface $authorization;

    public function __construct(AuthorizationInterface $authorization)
    {
        $this->authorization = $authorization;
    }

    public function __invoke(MvcAuthEvent $mvcAuthEvent): ?bool
    {
        if ($mvcAuthEvent->isAuthorized()) {
            return null;
        }

        $mvcEvent = $mvcAuthEvent->getMvcEvent();

        $request = $mvcEvent->getRequest();
        if (!$request instanceof Request) {
            return null;
        }

        $response = $mvcEvent->getResponse();
        if (!$response instanceof Response) {
            return null;
        }

        $routeMatch = $mvcEvent->getRouteMatch();
        if (!$routeMatch instanceof RouteMatch) {
            return null;
        }

        $identity = $mvcAuthEvent->getIdentity();
        if (!$identity instanceof IdentityInterface) {
            return null;
        }

        $resource = $mvcAuthEvent->getResource();
        $identity = $mvcAuthEvent->getIdentity();
        return $this->authorization->isAuthorized(identity: $identity, resource: $resource, privilege: $request->getMethod());
    }
}
