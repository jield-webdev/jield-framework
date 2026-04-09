<?php

declare(strict_types=1);

namespace Jield\Authentication\Adapter;

interface ValidatableAdapterInterface extends AdapterInterface
{
    /**
     * Returns the identity of the account being authenticated, or
     * NULL if none is set.
     *
     * @return mixed
     */
    public function getIdentity(): mixed;

    /**
     * Sets the identity for binding
     *
     * @param  mixed                       $identity
     * @return ValidatableAdapterInterface
     */
    public function setIdentity(mixed $identity): ValidatableAdapterInterface;

    /**
     * Returns the credential of the account being authenticated, or
     * NULL if none is set.
     *
     * @return mixed
     */
    public function getCredential(): mixed;

    /**
     * Sets the credential for binding
     *
     * @param  mixed                       $credential
     * @return ValidatableAdapterInterface
     */
    public function setCredential(mixed $credential): ValidatableAdapterInterface;
}
