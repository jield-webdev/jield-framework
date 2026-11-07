<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authorization;

use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Laminas\Permissions\Acl\Acl;
use Override;
use Webmozart\Assert\Assert;

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
        if (null !== $resource && !$this->hasResource($resource)) {
            $this->addResource($resource);
        }

        if (!$this->hasRole($identity)) {
            $this->addRole($identity);
        }

        return $this->isAllowed($identity, $resource, Assert::nullOrString($privilege));
    }
}
