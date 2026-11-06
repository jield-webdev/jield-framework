<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Jield\AssetManager\Resolver\AggregateResolver;
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
        /** @var mixed $config */
        $config = $container->get('config');
        $assetManagerConfig = [];

        if (!empty($config['asset_manager'])) {
            /** @var mixed $assetManagerConfig */
            $assetManagerConfig = $config['asset_manager'];
        }

        $assetManager = new AssetManager(
            resolver: \Jield\Stdlib\Service::get($container, AggregateResolver::class),
            config: \Jield\Stdlib\Value::array($assetManagerConfig),
        );

        $assetManager->setAssetFilterManager(filterManager: \Jield\Stdlib\Service::get($container, AssetFilterManager::class));

        $assetManager->setAssetCacheManager(cacheManager: \Jield\Stdlib\Service::get($container, AssetCacheManager::class));

        return $assetManager;
    }
}
