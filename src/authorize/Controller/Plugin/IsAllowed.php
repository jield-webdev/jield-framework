<?php

declare(strict_types=1);

namespace Jield\Authorize\Controller\Plugin;

use Jield\Authorize\Service\AuthorizeService;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Jield\Stdlib\Value;
use Laminas\Permissions\Acl\Resource\ResourceInterface;

/**
 * IsAllowed Controller plugin. Allows checking access to a resource/privilege in controllers.
 */
class IsAllowed extends AbstractPlugin
{
    public function __construct(
        protected AuthorizeService $authorizeService,
    ) {}

    /**
     * @param mixed $resource
     * @param mixed $privilege
     * @return bool
     */
    public function __invoke(mixed $resource, mixed $privilege = null): bool
    {
        return $this->authorizeService->isAllowed(
            resource: Value::instanceOrString(
                $resource,
                ResourceInterface::class,
            ),
            privilege: Value::nullableString($privilege),
        );
    }
}
