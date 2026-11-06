<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Authorize\Role\UserAsRoleInterface;

interface AccessRolesByUserInterface
{
    /** @return array<array-key, \Laminas\Permissions\Acl\Role\RoleInterface|string> */
    public function getAccessRolesByUser(UserAsRoleInterface $user): array;
}
