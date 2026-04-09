<?php

declare(strict_types=1);

namespace Jield\Authorize\Role;

use Jield\Authorize\Exception;
use Laminas\Permissions\Acl\Role\RoleInterface;
use function is_string;

/**
 * Base role object
 */
class Role implements RoleInterface, HierarchicalRoleInterface
{
    /** @var string */
    protected string $roleId;

    protected null|string|RoleInterface $parent;

    /**
     * @param string|null $roleId
     * @param string|RoleInterface|null $parent
     */
    public function __construct(?string $roleId = null, null|string|RoleInterface $parent = null)
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

        if ($parent instanceof RoleInterface) {
            $this->parent = $parent;

            return $this;
        }

        throw Exception\InvalidRoleException::invalidRoleInstance(role: $parent);
    }
}
