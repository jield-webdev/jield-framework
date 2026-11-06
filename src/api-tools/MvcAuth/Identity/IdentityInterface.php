<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Identity;

use Laminas\Permissions\Acl\Role\RoleInterface as AclRoleInterface;
use Laminas\Permissions\Rbac\RoleInterface as RbacRoleInterface;

interface IdentityInterface extends AclRoleInterface, RbacRoleInterface
{
    public function getAuthenticationIdentity();
}
