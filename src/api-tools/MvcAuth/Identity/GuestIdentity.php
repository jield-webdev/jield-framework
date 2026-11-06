<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Identity;

use Laminas\Permissions\Rbac\Role;
use Override;

class GuestIdentity extends Role implements IdentityInterface
{
    /** @var string */
    protected static string $identity = 'guest';

    public function __construct()
    {
        parent::__construct(name: static::$identity);
    }

    /** @return string */
    public function getRoleId(): string
    {
        return static::$identity;
    }

    /** @return null */
    #[Override]
    public function getAuthenticationIdentity(): null
    {
        return null;
    }
}
