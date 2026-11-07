<?php

declare(strict_types=1);

namespace Jield\Authorize\Role;

use Laminas\Permissions\Acl\Role\RoleInterface;

/**
 * Interface for a role with a possible parent role.
 */
interface HierarchicalRoleInterface extends RoleInterface
{
    /**
     * Get the parent role
     */
    public function getParent(): ?RoleInterface;
}
