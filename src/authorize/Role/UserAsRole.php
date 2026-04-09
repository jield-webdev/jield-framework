<?php

namespace Jield\Authorize\Role;

use Laminas\Permissions\Acl\Role\RoleInterface;

class UserAsRole implements RoleInterface
{
    private mixed $userAsRole;

    public function __construct(mixed $userAsRole)
    {
        $this->userAsRole = $userAsRole;
    }

    public function hasLoggedinUser(): bool
    {
        return null !== $this->userAsRole;
    }

    public function getLoggedInUser(): UserAsRoleInterface
    {
        if (null === $this->userAsRole) {
            throw new \RuntimeException(
                message: 'You are trying to get the logged in user, but no user is set, are you logged in and did you check the isLoggedIn()'
            );
        }

        return $this->userAsRole;
    }

    public function __toString()
    {
        return $this->getRoleId();
    }

    public function getRoleId(): string
    {
        if (!$this->userAsRole instanceof UserAsRoleInterface) {
            return 'guest';
        }

        return $this->userAsRole->getUserId();
    }
}
