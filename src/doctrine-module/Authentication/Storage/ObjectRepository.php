<?php

declare(strict_types=1);

namespace DoctrineModule\Authentication\Storage;

use DoctrineModule\Options\Authentication as AuthenticationOptions;
use Jield\Stdlib\Value;
use Laminas\Authentication\Storage\StorageInterface;

/**
 * This class implements StorageInterface and allow to save the result of an authentication against an object repository
 */
class ObjectRepository implements StorageInterface
{
    protected AuthenticationOptions $options;

    /** @param array|AuthenticationOptions $options */
    public function setOptions(array|AuthenticationOptions $options): ObjectRepository
    {
        if (!$options instanceof AuthenticationOptions) {
            $options = new AuthenticationOptions(options: Value::stringKeyedArray($options));
        }

        $this->options = $options;

        return $this;
    }

    /**
     * Constructor
     *
     * @param array|AuthenticationOptions $options
     */
    public function __construct(array|AuthenticationOptions $options = [])
    {
        $this->setOptions(options: $options);
    }

    public function isEmpty(): bool
    {
        return Value::instance($this->options->getStorage(), StorageInterface::class)->isEmpty();
    }

    /**
     * This function assumes that the storage only contains identifier values (which is the case if
     * the ObjectRepository authentication adapter is used).
     */
    public function read(): ?object
    {
        /** @var mixed $identity */
        $identity = Value::instance($this->options->getStorage(), StorageInterface::class)->read();
        if ($identity) {
            return $this->options->getObjectRepository()->find(id: $identity);
        }

        return null;
    }

    /**
     * Will return the key of the identity. If only the key is needed, this avoids an
     * unnecessary db call
     */
    public function readKeyOnly(): mixed
    {
        /** @var mixed $identity */
        return $identity = Value::instance($this->options->getStorage(), StorageInterface::class)->read();
    }

    public function write(mixed $contents): void
    {
        $metadataInfo = $this->options->getClassMetadata();
        $identifierValues = $metadataInfo->getIdentifierValues(Value::object($contents));

        Value::instance($this->options->getStorage(), StorageInterface::class)->write(
            contents: $identifierValues,
        );
    }

    public function clear(): void
    {
        Value::instance($this->options->getStorage(), StorageInterface::class)->clear();
    }
}
