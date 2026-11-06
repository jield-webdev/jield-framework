<?php

declare(strict_types=1);

namespace Jield\Authorize\View\Helper;

use Jield\Authorize\Service\AuthorizeService as Authorize;

/**
 * IsAllowed View helper. Allows checking access to a resource/privilege in views.
 */
class IsAllowed
{
    /** @var Authorize */
    protected Authorize $authorizeService;

    public function __construct(Authorize $authorizeService)
    {
        $this->authorizeService = $authorizeService;
    }

    /**
     * @param mixed $resource
     * @param mixed $privilege
     * @return bool
     */
    public function __invoke(mixed $resource, mixed $privilege = null): bool
    {
        return $this->authorizeService->isAllowed(resource: \Jield\Stdlib\Value::instanceOrString($resource, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), privilege: \Jield\Stdlib\Value::nullableString($privilege));
    }
}
