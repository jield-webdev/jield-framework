<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authorization;

use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Jield\Stdlib\Value;
use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Override;

/**
 * Authorization implementation that uses the ACL component
 */
class AclAuthorization extends Acl implements AuthorizationInterface
{
    /**
     * Is the provided identity authorized for the given privilege on the given resource?
     *
     * If the resource does not exist, adds it, the proxies to isAllowed().
     *
     * @param mixed $resource
     * @param mixed $privilege
     */
    #[Override]
    public function isAuthorized(IdentityInterface $identity, mixed $resource, mixed $privilege): bool
    {
        if (
            null !== $resource
            && !$this->hasResource(Value::instanceOrString(
                $resource,
                ResourceInterface::class,
            ))
        ) {
            $this->addResource(Value::instanceOrString(
                $resource,
                ResourceInterface::class,
            ));
        }

        if (!$this->hasRole($identity)) {
            $this->addRole($identity);
        }

        return $this->isAllowed(
            $identity,
            Value::nullableInstanceOrString(
                $resource,
                ResourceInterface::class,
            ),
            Value::nullableString($privilege),
        );
    }
}
