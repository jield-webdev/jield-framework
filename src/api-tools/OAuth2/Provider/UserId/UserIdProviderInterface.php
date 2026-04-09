<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Provider\UserId;

use Jield\Stdlib\RequestInterface;

interface UserIdProviderInterface
{
    /**
     * Return the current authenticated user identifier.
     *
     */
    public function __invoke(RequestInterface $request): mixed;
}
