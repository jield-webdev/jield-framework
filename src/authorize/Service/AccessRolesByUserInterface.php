<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Authorize\Role\UserAsRoleInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;

interface AccessRolesByUserInterface
{
    /** @return array<array-key, RoleInterface|string> */
    public function getAccessRolesByUser(UserAsRoleInterface $user): array;
}
