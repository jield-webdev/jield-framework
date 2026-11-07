<?php

declare(strict_types=1);

namespace Jield\AssetManager\Cache;

use Assetic\Contracts\Cache\CacheInterface;
use Laminas\Cache\Storage\StorageInterface;
use Override;
use Webmozart\Assert\Assert;

/**
 * Jield Cache Storage Adapter for Assetic
 */
class LaminasCacheAdapter implements CacheInterface
{
    /**
     * @param StorageInterface $jieldCache Jield Configured Cache Storage
     */
    public function __construct(
        protected StorageInterface $jieldCache,
    ) {}

    /**
     * {@inheritDoc}
     * @throws \Laminas\Cache\Exception\ExceptionInterface
     */
    #[Override]
    public function has($key)
    {
        return $this->jieldCache->hasItem(Assert::stringNotEmpty($key));
    }

    /**
     * {@inheritDoc}
     * @throws \Laminas\Cache\Exception\ExceptionInterface
     */
    #[Override]
    public function get($key)
    {
        return Assert::nullOrString($this->jieldCache->getItem(Assert::stringNotEmpty($key)));
    }

    /**
     * {@inheritDoc}
     * @throws \Laminas\Cache\Exception\ExceptionInterface
     */
    #[Override]
    public function set($key, $value)
    {
        return $this->jieldCache->setItem(Assert::stringNotEmpty($key), $value);
    }

    /**
     * {@inheritDoc}
     * @throws \Laminas\Cache\Exception\ExceptionInterface
     */
    #[Override]
    public function remove($key)
    {
        return $this->jieldCache->removeItem(Assert::stringNotEmpty($key));
    }
}
