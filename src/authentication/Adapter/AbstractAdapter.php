<?php

declare(strict_types=1);

namespace Jield\Authentication\Adapter;

use Override;

abstract class AbstractAdapter implements ValidatableAdapterInterface
{
    /** @var mixed */
    protected mixed $credential;

    /** @var mixed */
    protected mixed $identity;

    /**
     * Returns the credential of the account being authenticated, or
     * NULL if none is set.
     *
     * @return mixed
     */
    #[Override]
    public function getCredential(): mixed
    {
        return $this->credential;
    }

    /**
     * Sets the credential for binding
     *
     * @param mixed $credential
     * @return self Provides a fluent interface
     */
    #[Override]
    public function setCredential(mixed $credential): static
    {
        $this->credential = $credential;

        return $this;
    }

    /**
     * Returns the identity of the account being authenticated, or
     * NULL if none is set.
     *
     * @return mixed
     */
    #[Override]
    public function getIdentity(): mixed
    {
        return $this->identity;
    }

    /**
     * Sets the identity for binding
     *
     * @param mixed $identity
     * @return self Provides a fluent interface
     */
    #[Override]
    public function setIdentity(mixed $identity): static
    {
        $this->identity = $identity;

        return $this;
    }
}
