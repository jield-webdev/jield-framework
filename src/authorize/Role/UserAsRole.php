<?php

declare(strict_types=1);

namespace Jield\Authorize\Role;

use Laminas\Permissions\Acl\Role\RoleInterface;
use RuntimeException;
use Stringable;

class UserAsRole implements RoleInterface, Stringable
{
    public function __construct(
        private readonly mixed $userAsRole,
    ) {
    }

    public function hasLoggedinUser(): bool
    {
        return null !== $this->userAsRole;
    }

    /**
     * @throws RuntimeException
     */
    public function getLoggedInUser(): UserAsRoleInterface
    {
        if (null === $this->userAsRole) {
            throw new RuntimeException(
                message: 'You are trying to get the logged in user, but no user is set, are you logged in and did you check the isLoggedIn()',
            );
        }

        return \Webmozart\Assert\Assert::isInstanceOf(
            $this->userAsRole,
            \Jield\Authorize\Role\UserAsRoleInterface::class,
        );
    }

    public function __toString(): string
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
