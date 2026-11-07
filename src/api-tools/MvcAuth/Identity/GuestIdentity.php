<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Identity;

use Laminas\Permissions\Rbac\Role;
use Override;

class GuestIdentity extends Role implements IdentityInterface
{
    protected static string $identity = 'guest';

    public function __construct()
    {
        parent::__construct(name: static::$identity);
    }

    public function getRoleId(): string
    {
        return static::$identity;
    }

    #[Override]
    public function getAuthenticationIdentity(): null
    {
        return null;
    }
}
