<?php

declare(strict_types=1);

namespace Jield\Authorize\Permissions\Acl\Assertion;

use Doctrine\Common\Collections\Collection;
use Jield\Authorize\Provider\Identity\AuthenticationIdentityProvider;
use Jield\Authorize\Role\UserAsRole;
use Jield\Authorize\Role\UserAsRoleInterface;
use Jield\Mvc\Router\RouteMatch;
use Jield\Stdlib\Service;
use Laminas\Http\Request;
use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Laminas\Stdlib\RequestInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Webmozart\Assert\Assert;

abstract class AbstractAssertion implements AssertionInterface
{
    protected AuthenticationIdentityProvider $authenticationIdentityProvider;

    protected UserAsRole $userAsRole;

    protected RouteMatch $routeMatch;

    protected RequestInterface|Request $request;

    public function __construct(
        protected ContainerInterface $container,
    ) {
        $this->authenticationIdentityProvider = Service::get($this->container, AuthenticationIdentityProvider::class);
    }

    public function setRouteMatch(RouteMatch $routeMatch): AbstractAssertion
    {
        $this->routeMatch = $routeMatch;
        return $this;
    }

    public function setRequest(RequestInterface $request): AbstractAssertion
    {
        $this->request = $request;
        return $this;
    }

    protected function hasPermit($entity, string|array $privilege): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        return $this->authenticationIdentityProvider->hasPermit(resource: Assert::object($entity), privilege: $privilege);
    }

    protected function isLoggedIn(): bool
    {
        return $this->authenticationIdentityProvider->hasIdentity();
    }

    protected function hasGeneralPermit(string $className, string $privilege): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        return $this->authenticationIdentityProvider->hasGeneralPermit(className: $className, privilege: $privilege);
    }

    protected function getIdentity(): UserAsRoleInterface
    {
        if (!$this->authenticationIdentityProvider->hasIdentity()) {
            throw new RuntimeException(message: 'Calling getIdentity when nog logged in is not possible');
        }

        return Assert::isInstanceOf(
            $this->authenticationIdentityProvider->getIdentity(),
            \Jield\Authorize\Role\UserAsRoleInterface::class,
        );
    }

    protected function hasRole(string|array|int|Collection $roles): bool
    {
        return $this->authenticationIdentityProvider->hasRole(roles: $roles);
    }

    protected function parsePrivilege(?string $privilege): string
    {
        //Short circuit the function in case the privilege is known via the acl
        if (null !== $privilege) {
            return $privilege;
        }

        if (!isset($this->routeMatch)) {
            throw new RuntimeException(message: 'Calling parsePrivilege before setting the routematch is not possible');
        }

        return Assert::string($this->routeMatch->getParam(name: 'privilege') ?? $this->routeMatch->getParam(name: 'action'));
    }
}
