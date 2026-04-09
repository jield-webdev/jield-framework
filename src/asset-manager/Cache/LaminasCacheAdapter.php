<?php

namespace Jield\AssetManager\Cache;

use Assetic\Contracts\Cache\CacheInterface;
use Jield\Cache\Storage\StorageInterface;
use Override;

/**
 * Jield Cache Storage Adapter for Assetic
 */
class LaminasCacheAdapter implements CacheInterface
{

    /** @var StorageInterface */
    protected StorageInterface $jieldCache;

    /**
     * Constructor
     *
     * @param StorageInterface $jieldCache Jield Configured Cache Storage
     */
    public function __construct(StorageInterface $jieldCache)
    {
        $this->jieldCache = $jieldCache;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function has($key)
    {
        return $this->jieldCache->hasItem($key);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get($key)
    {
        return $this->jieldCache->getItem($key);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function set($key, $value)
    {
        return $this->jieldCache->setItem($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function remove($key)
    {
        return $this->jieldCache->removeItem($key);
    }
}
