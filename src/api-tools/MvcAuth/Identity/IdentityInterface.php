<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Identity;

use Jield\Permissions\Acl\Role\RoleInterface as AclRoleInterface;
use Jield\Permissions\Rbac\RoleInterface as RbacRoleInterface;

interface IdentityInterface extends
    AclRoleInterface,
    RbacRoleInterface
{
    public function getAuthenticationIdentity();
}
