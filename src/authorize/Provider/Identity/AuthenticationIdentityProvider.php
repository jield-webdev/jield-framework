<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Identity;

use Doctrine\Common\Collections\Collection;
use JetBrains\PhpStorm\Pure;
use Jield\Authorize\Role\UserAsRole;
use Jield\Authorize\Role\UserAsRoleInterface;
use Jield\Authorize\Service\AccessRolesByUserInterface;
use Jield\Authorize\Service\HasPermitInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Webmozart\Assert\Assert;

/**
 * Simple identity provider to handle simply guest|user
 */
class AuthenticationIdentityProvider implements ProviderInterface
{
    public const string ACCESS_PUBLIC = 'public';
    public const string ACCESS_AUTHENTICATED = 'user';

    protected RoleInterface|string $defaultRole;
    protected RoleInterface|string $authenticatedRole;

    #[Pure]
    public function __construct(
        protected AuthenticationService $authService,
        private readonly AccessRolesByUserInterface $accessOrUserService,
        private readonly HasPermitInterface $permitService,
        array $authorizeConfig,
    ) {
        $this->defaultRole = $authorizeConfig['default_role'] ?? self::ACCESS_PUBLIC;
        $this->authenticatedRole = $authorizeConfig['authenticated_role'] ?? self::ACCESS_AUTHENTICATED;
    }

    public function getIdentityAsRole(): UserAsRole
    {
        if (!$this->authService->hasIdentity()) {
            return new UserAsRole(userAsRole: null);
        }

        return new UserAsRole(userAsRole: $this->authService->getIdentity());
    }

    public function hasIdentity(): bool
    {
        return $this->authService->hasIdentity();
    }

    public function rolesHaveAccess(string|array $roles): bool
    {
        if (!is_array(value: $roles)) {
            $roles = [$roles];
        }

        return !empty(array_intersect($roles, $this->getIdentityRoles()));
    }

    public function getIdentityRoles(): array
    {
        if (!$this->authService->hasIdentity()) {
            return [$this->defaultRole];
        }

        return $this->accessOrUserService->getAccessRolesByUser(user: $this->authService->getIdentity());
    }

    public function hasPermit(object $resource, string|array $privilege): bool
    {
        return $this->permitService->hasPermit(
            user: Assert::isInstanceOf($this->getIdentity(), \Jield\Authorize\Role\UserAsRoleInterface::class),
            resource: $resource,
            privilege: $privilege,
        );
    }

    public function getIdentity(): ?UserAsRoleInterface
    {
        if (!$this->authService->hasIdentity()) {
            return null;
        }

        return $this->authService->getIdentity();
    }

    public function hasGeneralPermit(string $className, string $privilege): bool
    {
        return $this->permitService->hasGeneralPermit(
            user: Assert::isInstanceOf($this->getIdentity(), \Jield\Authorize\Role\UserAsRoleInterface::class),
            className: $className,
            privilege: $privilege,
        );
    }

    public function hasRole(string|array|int|Collection $roles): bool
    {
        if ($roles instanceof Collection) {
            $roles = $roles->map(func: static fn(RoleInterface $role) => $role->getRoleId())->toArray();
        }

        if (!is_array(value: $roles)) {
            $roles = [$roles];
        }

        return !empty(array_intersect($roles, $this->getIdentityRoles()));
    }
}
