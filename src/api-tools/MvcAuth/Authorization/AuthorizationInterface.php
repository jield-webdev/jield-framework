<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authorization;

use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;

interface AuthorizationInterface
{
    /**
     * Whether or not the given identity has the given privilege on the given resource.
     *
     */
    public function isAuthorized(IdentityInterface $identity, mixed $resource, mixed $privilege): bool;
}
