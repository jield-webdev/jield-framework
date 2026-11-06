<?php

declare(strict_types=1);

namespace Jield\AssetManager\Cache;

use Assetic\Contracts\Cache\CacheInterface;
use Laminas\Cache\Storage\StorageInterface;
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
        return $this->jieldCache->hasItem(\Jield\Stdlib\Value::nonEmptyString($key));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get($key)
    {
        return \Jield\Stdlib\Value::nullableString($this->jieldCache->getItem(\Jield\Stdlib\Value::nonEmptyString($key)));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function set($key, $value)
    {
        return $this->jieldCache->setItem(\Jield\Stdlib\Value::nonEmptyString($key), $value);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function remove($key)
    {
        return $this->jieldCache->removeItem(\Jield\Stdlib\Value::nonEmptyString($key));
    }
}
