<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authorization;

use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Jield\Mvc\Router\RouteMatch;
use Laminas\Http\Request;
use Laminas\Http\Response;

class DefaultAuthorizationListener
{
    public function __construct(
        protected AuthorizationInterface $authorization,
    ) {
    }

    /**
     * @throws \ReflectionException
     */
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

        /** @mago-var mixed $identity */
        $identity = $mvcAuthEvent->getIdentity();
        if (!$identity instanceof IdentityInterface) {
            return null;
        }

        $resource = $mvcAuthEvent->getResource();
        /** @mago-var mixed $identity */
        $identity = $mvcAuthEvent->getIdentity();
        return $this->authorization->isAuthorized(
            identity: \Webmozart\Assert\Assert::isInstanceOf(
                $identity,
                \Jield\ApiTools\MvcAuth\Identity\IdentityInterface::class,
            ),
            resource: $resource,
            privilege: $request->getMethod(),
        );
    }
}
