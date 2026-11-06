<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

interface AssetFilterManagerAwareInterface
{
    /**
     * Set the AssetFilterManager.
     */
    public function setAssetFilterManager(AssetFilterManager $filterManager);

    /**
     * Get the AssetFilterManager
     */
    public function getAssetFilterManager(): AssetFilterManager;
}
