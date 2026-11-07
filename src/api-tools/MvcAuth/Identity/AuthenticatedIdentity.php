<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Identity;

use Jield\Stdlib\Value;
use Laminas\Permissions\Rbac\Role;
use Override;

class AuthenticatedIdentity extends Role implements IdentityInterface
{
    public function __construct(
        protected mixed $identity,
    ) {
        parent::__construct(
            is_array($identity) && isset($identity['id']) ? Value::stringify($identity['id']) : 'authenticated',
        );
    }

    public function getRoleId(): int|string|null
    {
        return $this->name;
    }

    #[Override]
    public function getAuthenticationIdentity(): mixed
    {
        return $this->identity;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
