<?php

namespace Jield\Authorize\Service;

use Jield\Authorize\Role\UserAsRoleInterface;

interface AccessRolesByUserInterface
{
    public function getAccessRolesByUser(UserAsRoleInterface $user): array;
}
