<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Rule;

/**
 * Rule provider interface, allows specifying that an object
 * can provide ACL rules
 */
interface ProviderInterface
{
    public function getRules(): array;
}
