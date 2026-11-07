<?php

declare(strict_types=1);

namespace Jield\Authorize\Controller\Plugin;

use Jield\Authorize\Service\AuthorizeService;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * IsAllowed Controller plugin. Allows checking access to a resource/privilege in controllers.
 */
class IsAllowed extends AbstractPlugin
{
    public function __construct(
        protected AuthorizeService $authorizeService,
    ) {
    }

    public function __invoke(mixed $resource, mixed $privilege = null): bool
    {
        return $this->authorizeService->isAllowed(
            resource: \Jield\Stdlib\Value::instanceOrString(
                $resource,
                \Laminas\Permissions\Acl\Resource\ResourceInterface::class,
            ),
            privilege: \Webmozart\Assert\Assert::nullOrString($privilege),
        );
    }
}
