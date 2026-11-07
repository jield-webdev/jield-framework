<?php

declare(strict_types=1);

namespace Jield\Authorize\Role;

use Laminas\Permissions\Acl\Role\RoleInterface;

use function is_string;

/**
 * Base role object
 */
class Role implements RoleInterface, HierarchicalRoleInterface
{
    protected string $roleId;

    protected ?RoleInterface $parent = null;

    public function __construct(?string $roleId = null, string|RoleInterface|null $parent = null)
    {
        if (null !== $roleId) {
            $this->setRoleId(roleId: $roleId);
        }

        if (null !== $parent) {
            $this->setParent(parent: $parent);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRoleId(): string
    {
        return $this->roleId;
    }

    public function setRoleId(string $roleId): static
    {
        $this->roleId = $roleId;

        return $this;
    }

    public function getParent(): ?RoleInterface
    {
        return $this->parent;
    }

    public function setParent(string|RoleInterface|null $parent): static
    {
        if (null === $parent) {
            $this->parent = null;

            return $this;
        }

        if (is_string(value: $parent)) {
            $this->parent = new Role(roleId: $parent);

            return $this;
        }

        $this->parent = $parent;
        return $this;
    }
}
