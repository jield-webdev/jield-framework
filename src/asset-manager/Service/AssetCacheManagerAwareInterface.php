<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

interface AssetCacheManagerAwareInterface
{
    /**
     * Set the AssetCacheManager.
     */
    public function setAssetCacheManager(AssetCacheManager $cacheManager);

    /**
     * Get the AssetCacheManager
     */
    public function getAssetCacheManager(): AssetCacheManager;
}
