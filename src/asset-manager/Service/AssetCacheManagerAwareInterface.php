<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

interface AssetCacheManagerAwareInterface
{
    /**
     * Set the AssetCacheManager.
     *
     * @param AssetCacheManager $cacheManager
     */
    public function setAssetCacheManager(AssetCacheManager $cacheManager);

    /**
     * Get the AssetCacheManager
     *
     * @return AssetCacheManager
     */
    public function getAssetCacheManager(): AssetCacheManager;
}
