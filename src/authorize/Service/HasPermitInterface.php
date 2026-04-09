<?php

namespace Jield\Authorize\Service;

use Jield\Authorize\Role\UserAsRoleInterface;

interface HasPermitInterface
{
    public function hasPermit(UserAsRoleInterface $user, object $resource, array|string $privilege): bool;

    public function hasGeneralPermit(UserAsRoleInterface $user, string $className, string $privilege): bool;
}
