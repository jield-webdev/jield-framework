<?php

declare(strict_types=1);

namespace DoctrineModule\Authentication\Storage;

use DoctrineModule\Options\Authentication as AuthenticationOptions;
use Laminas\Authentication\Storage\StorageInterface;
use Webmozart\Assert\Assert;

/**
 * This class implements StorageInterface and allow to save the result of an authentication against an object repository
 */
class ObjectRepository implements StorageInterface
{
    protected AuthenticationOptions $options;

    public function setOptions(array|AuthenticationOptions $options): ObjectRepository
    {
        if (!$options instanceof AuthenticationOptions) {
            $options = new AuthenticationOptions(options: \Jield\Stdlib\Value::options($options));
        }

        $this->options = $options;

        return $this;
    }

    public function __construct(array|AuthenticationOptions $options = [])
    {
        $this->setOptions(options: $options);
    }

    public function isEmpty(): bool
    {
        return Assert::isInstanceOf($this->options->getStorage(), StorageInterface::class)->isEmpty();
    }

    /**
     * This function assumes that the storage only contains identifier values (which is the case if
     * the ObjectRepository authentication adapter is used).
     */
    public function read(): ?object
    {
        /** @mago-var mixed $identity */
        $identity = Assert::isInstanceOf($this->options->getStorage(), StorageInterface::class)->read();
        if ($identity) {
            return $this->options->getObjectRepository()->find(id: $identity);
        }

        return null;
    }

    /**
     * Will return the key of the identity. If only the key is needed, this avoids an
     * unnecessary db call
     *
     * @throws \Laminas\Authentication\Exception\ExceptionInterface
     */
    public function readKeyOnly(): mixed
    {
        /** @mago-var mixed $identity */
        return $identity = Assert::isInstanceOf($this->options->getStorage(), StorageInterface::class)->read();
    }

    public function write(mixed $contents): void
    {
        $metadataInfo = $this->options->getClassMetadata();
        $identifierValues = $metadataInfo->getIdentifierValues(Assert::object($contents));

        Assert::isInstanceOf($this->options->getStorage(), StorageInterface::class)->write(contents: $identifierValues);
    }

    public function clear(): void
    {
        Assert::isInstanceOf($this->options->getStorage(), StorageInterface::class)->clear();
    }
}
