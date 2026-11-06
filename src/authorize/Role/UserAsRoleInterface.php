<?php

declare(strict_types=1);

namespace Jield\Authorize\Role;

interface UserAsRoleInterface
{
    public function getUserId(): string;
}
