<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Identity;

use Jield\Permissions\Rbac\Role;
use Override;

class AuthenticatedIdentity extends Role implements IdentityInterface
{
    public function __construct(protected array $identity)
    {
        parent::__construct((string)$identity['id']);
    }

    public function getRoleId(): null|int|string
    {
        return $this->name;
    }

    #[Override]
    public function getAuthenticationIdentity(): array
    {
        return $this->identity;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
