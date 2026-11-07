<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Jield\AssetManager\Resolver\AggregateResolver;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Factory class for AssetManagerService
 *
 * @category   AssetManager
 * @package    AssetManager
 */
class AssetManagerServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AssetManager
    {
        $config = $container->get('config');
        $assetManagerConfig = [];

        if (!empty($config['asset_manager'])) {
            $assetManagerConfig = $config['asset_manager'];
        }

        $assetManager = new AssetManager(
            resolver: Service::get($container, AggregateResolver::class),
            config: $assetManagerConfig,
        );

        $assetManager->setAssetFilterManager(filterManager: Service::get($container, AssetFilterManager::class));

        $assetManager->setAssetCacheManager(cacheManager: Service::get($container, AssetCacheManager::class));

        return $assetManager;
    }
}
