<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Override;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

/**
 * Factory for the Asset Cache Manager Service
 *
 * @package Jield\AssetManager\Service
 */
class AssetCacheManagerServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AssetCacheManager
    {
        $config = [];

        $globalConfig = $container->get('config');

        if (!empty($globalConfig['asset_manager']['caching'])) {
            $config = $globalConfig['asset_manager']['caching'];
        }

        return new AssetCacheManager(
            container: Assert::isInstanceOf($container, ServiceLocatorInterface::class),
            config: $config,
        );
    }
}
