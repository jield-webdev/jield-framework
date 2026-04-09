<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Identity;

use Laminas\Permissions\Acl\Role\RoleInterface;

/**
 * Interface for identity providers, which are objects capable of
 * retrieving an active identity's role
 */
interface ProviderInterface
{
    /**
     * Retrieve roles for the current identity
     *
     * @return string[]|RoleInterface[]
     */
    public function getIdentityRoles(): array;
}
