<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authorization;

use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Laminas\Permissions\Acl\Acl;
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
        if (null !== $resource && !$this->hasResource(\Jield\Stdlib\Value::instanceOrString($resource, \Laminas\Permissions\Acl\Resource\ResourceInterface::class))) {
            $this->addResource(\Jield\Stdlib\Value::instanceOrString($resource, \Laminas\Permissions\Acl\Resource\ResourceInterface::class));
        }

        if (!$this->hasRole($identity)) {
            $this->addRole($identity);
        }

        return $this->isAllowed($identity, \Jield\Stdlib\Value::nullableInstanceOrString($resource, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), \Jield\Stdlib\Value::nullableString($privilege));
    }
}
