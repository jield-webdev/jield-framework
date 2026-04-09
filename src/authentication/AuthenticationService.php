<?php

declare(strict_types=1);

namespace Jield\Authentication;

use Override;
use function assert;

class AuthenticationService implements AuthenticationServiceInterface
{
    /**
     * Persistent storage handler
     *
     * @var Storage\StorageInterface|null
     */
    protected ?Storage\StorageInterface $storage;

    /**
     * Authentication adapter
     *
     * @var Adapter\AdapterInterface|null
     */
    protected ?Adapter\AdapterInterface $adapter;

    /**
     * Constructor
     */
    public function __construct(?Storage\StorageInterface $storage = null, ?Adapter\AdapterInterface $adapter = null)
    {
        if (null !== $storage) {
            $this->setStorage(storage: $storage);
        }
        if (null !== $adapter) {
            $this->setAdapter(adapter: $adapter);
        }
    }

    /**
     * Returns the authentication adapter
     *
     * The adapter does not have a default if the storage adapter has not been set.
     *
     * @return Adapter\AdapterInterface|null
     */
    public function getAdapter(): ?Adapter\AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Sets the authentication adapter
     *
     * @return self Provides a fluent interface
     */
    public function setAdapter(Adapter\AdapterInterface $adapter): static
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Returns the persistent storage handler
     *
     * Session storage is used by default unless a different storage adapter has been set.
     *
     * @return Storage\StorageInterface
     */
    public function getStorage(): ?Storage\StorageInterface
    {
        if (null === $this->storage) {
            $this->setStorage(storage: new Storage\Session());
        }

        assert(assertion: $this->storage !== null);

        return $this->storage;
    }

    /**
     * Sets the persistent storage handler
     *
     * @return self Provides a fluent interface
     */
    public function setStorage(Storage\StorageInterface $storage): static
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * Authenticates against the supplied adapter
     *
     * @return Result
     * @throws Exception\RuntimeException
     */
    #[Override]
    public function authenticate(?Adapter\AdapterInterface $adapter = null): Result
    {
        if (!$adapter && !$adapter = $this->getAdapter()) {
            throw new Exception\RuntimeException(
                message: 'An adapter must be set or passed prior to calling authenticate()'
            );
        }
        $result = $adapter->authenticate();

        /**
         * Jield-7546 - prevent multiple successive calls from storing inconsistent results
         * Ensure storage has clean state
         */
        if ($this->hasIdentity()) {
            $this->clearIdentity();
        }

        if ($result->isValid()) {
            $this->getStorage()->write(contents: $result->getIdentity());
        }

        return $result;
    }

    /**
     * Returns true if and only if an identity is available from storage
     *
     * @return bool
     */
    #[Override]
    public function hasIdentity(): bool
    {
        return !$this->getStorage()->isEmpty();
    }

    /**
     * Returns the identity from storage or null if no identity is available
     *
     * @return mixed
     */
    #[Override]
    public function getIdentity(): mixed
    {
        $storage = $this->getStorage();

        if ($storage->isEmpty()) {
            return null;
        }

        return $storage->read();
    }

    /**
     * Clears the identity from persistent storage
     *
     * @return void
     */
    #[Override]
    public function clearIdentity(): void
    {
        $this->getStorage()->clear();
    }
}
