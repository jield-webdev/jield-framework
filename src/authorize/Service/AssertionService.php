<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Stdlib\Service;
use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webmozart\Assert\Assert;

class AssertionService
{
    private readonly Acl $acl;

    private readonly AuthorizeService $authorizeService;

    /**
     * @throws ContainerExceptionInterface
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
        if (!$this->acl->hasResource(\Jield\Stdlib\Value::instanceOrString($entity, \Laminas\Permissions\Acl\Resource\ResourceInterface::class))) {
            $this->acl->addResource(\Jield\Stdlib\Value::instanceOrString($entity, \Laminas\Permissions\Acl\Resource\ResourceInterface::class));
            $this->acl->allow(
                [],
                \Jield\Stdlib\Value::instanceOrArrayOrString($entity, \Laminas\Permissions\Acl\Resource\ResourceInterface::class),
                [],
                Assert::isInstanceOf($this->container->build(name: $assertion), AssertionInterface::class),
            );
        }
    }

    public function isUserAllowed($entity, string $privilege): bool
    {
        return $this->authorizeService->isAllowed(resource: \Jield\Stdlib\Value::instanceOrString($entity, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), privilege: $privilege);
    }
}
