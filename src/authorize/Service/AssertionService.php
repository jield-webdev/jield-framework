<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Laminas\Permissions\Acl\Acl;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AssertionService
{
    private Acl $acl;

    private AuthorizeService $authorizeService;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(private ServiceManager $container)
    {
        /** @var AuthorizeService $authorizeService */
        $authorizeService       = $this->container->get(id: AuthorizeService::class);
        $this->authorizeService = $authorizeService;
        $this->acl              = $this->authorizeService->getAcl();
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function addResource($entity, string $assertion): void
    {
        if (!$this->acl->hasResource($entity)) {
            $this->acl->addResource($entity);
            $this->acl->allow([], $entity, [], $this->container->build(name: $assertion));
        }
    }

    public function isUserAllowed($entity, string $privilege): bool
    {
        return $this->authorizeService->isAllowed(resource: $entity, privilege: $privilege);
    }
}
