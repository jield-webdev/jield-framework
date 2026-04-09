<?php

declare(strict_types=1);

namespace Jield\Authentication;

/**
 * Provides an API for authentication and identity management
 */
interface AuthenticationServiceInterface
{
    /**
     * Authenticates and provides an authentication result
     *
     * @return Result
     */
    public function authenticate(): Result;

    /**
     * Returns true if and only if an identity is available
     *
     * @return bool
     */
    public function hasIdentity(): bool;

    /**
     * Returns the authenticated identity or null if no identity is available
     *
     * @return mixed
     */
    public function getIdentity(): mixed;

    /**
     * Clears the identity
     *
     * @return void
     */
    public function clearIdentity(): void;
}
