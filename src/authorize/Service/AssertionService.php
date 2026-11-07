<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AssertionService
{
    private readonly Acl $acl;

    private readonly AuthorizeService $authorizeService;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        private readonly ServiceManager $container,
    ) {
        $authorizeService = Service::get($this->container, AuthorizeService::class);
        $this->authorizeService = $authorizeService;
        $this->acl = $this->authorizeService->getAcl();
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function addResource($entity, string $assertion): void
    {
        if (!$this->acl->hasResource(Value::instanceOrString(
            $entity,
            ResourceInterface::class,
        ))) {
            $this->acl->addResource(Value::instanceOrString(
                $entity,
                ResourceInterface::class,
            ));
            $this->acl->allow(
                [],
                Value::instanceOrArrayOrString(
                    $entity,
                    ResourceInterface::class,
                ),
                [],
                Value::instance(
                    $this->container->build(name: $assertion),
                    AssertionInterface::class,
                ),
            );
        }
    }

    public function isUserAllowed($entity, string $privilege): bool
    {
        return $this->authorizeService->isAllowed(
            resource: Value::instanceOrString(
                $entity,
                ResourceInterface::class,
            ),
            privilege: $privilege,
        );
    }
}
