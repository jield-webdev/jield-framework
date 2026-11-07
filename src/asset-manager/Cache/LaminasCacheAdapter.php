<?php

declare(strict_types=1);

namespace Jield\AssetManager\Cache;

use Assetic\Contracts\Cache\CacheInterface;
use Jield\Stdlib\Value;
use Laminas\Cache\Storage\StorageInterface;
use Override;

/**
 * Jield Cache Storage Adapter for Assetic
 */
class LaminasCacheAdapter implements CacheInterface
{
    /**
     * Constructor
     *
     * @param StorageInterface $jieldCache Jield Configured Cache Storage
     */
    public function __construct(
        protected StorageInterface $jieldCache,
    ) {}

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function has($key)
    {
        return $this->jieldCache->hasItem(Value::nonEmptyString($key));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get($key)
    {
        return Value::nullableString($this->jieldCache->getItem(Value::nonEmptyString(
            $key,
        )));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function set($key, $value)
    {
        return $this->jieldCache->setItem(Value::nonEmptyString($key), $value);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function remove($key)
    {
        return $this->jieldCache->removeItem(Value::nonEmptyString($key));
    }
}
