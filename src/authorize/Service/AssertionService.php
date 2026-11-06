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
    public function __construct(
        private ServiceManager $container,
    ) {
        
        $authorizeService = \Jield\Stdlib\Service::get($this->container, AuthorizeService::class);
        $this->authorizeService = $authorizeService;
        $this->acl = $this->authorizeService->getAcl();
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function addResource($entity, string $assertion): void
    {
        if (!$this->acl->hasResource(\Jield\Stdlib\Value::instanceOrString($entity, \Laminas\Permissions\Acl\Resource\ResourceInterface::class))) {
            $this->acl->addResource(\Jield\Stdlib\Value::instanceOrString($entity, \Laminas\Permissions\Acl\Resource\ResourceInterface::class));
            $this->acl->allow([], \Jield\Stdlib\Value::instanceOrArrayOrString($entity, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), [], $this->container->build(name: $assertion));
        }
    }

    public function isUserAllowed($entity, string $privilege): bool
    {
        return $this->authorizeService->isAllowed(resource: \Jield\Stdlib\Value::instanceOrString($entity, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), privilege: $privilege);
    }
}
