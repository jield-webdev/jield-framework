<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Role;

use Jield\Authorize\Role\HierarchicalRoleInterface;
use Jield\Authorize\Role\Role;
use Doctrine\Persistence\ObjectRepository;
use Laminas\Permissions\Acl\Role\RoleInterface;
use function array_values;

/**
 * Role provider based on a {@see \Doctrine\Persistence\ObjectRepository}
 */
class ObjectRepositoryProvider implements ProviderInterface
{
    /** @var ObjectRepository */
    protected ObjectRepository $objectRepository;

    public function __construct(ObjectRepository $objectRepository)
    {
        $this->objectRepository = $objectRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function getRoles(): array
    {
        $result = $this->objectRepository->findAll();
        $roles  = [];

        // Pass One: Build each object
        foreach ($result as $role) {
            if (! $role instanceof RoleInterface) {
                continue;
            }

            $roleId = $role->getRoleId();
            $parent = null;

            if ($role instanceof HierarchicalRoleInterface && $parent = $role->getParent()) {
                $parent = $parent->getRoleId();
            }

            $roles[$roleId] = new Role(roleId: $roleId, parent: $parent);
        }

        // Pass Two: Re-inject parent objects to preserve hierarchy
        /** @var Role $roleObj */
        foreach ($roles as $roleObj) {
            $parentRoleObj = $roleObj->getParent();

            if ($parentRoleObj && $parentRoleObj->getRoleId()) {
                $roleObj->setParent(parent: $roles[$parentRoleObj->getRoleId()]);
            }
        }

        return array_values(array: $roles);
    }
}
