<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Authorize\Service\AuthorizeService;

/**
 * Interface for Authorize-aware objects. Allows injection
 * of an {@see \Jield\Authorize\Service\AuthorizeService}
 */
interface AuthorizeAwareInterface
{
    /**
     * @return void
     */
    public function setAuthorizeService(AuthorizeService $auth): void;
}
