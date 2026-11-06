<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

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

        /** @var mixed $globalConfig */
        $globalConfig = $container->get('config');

        if (!empty($globalConfig['asset_manager']['caching'])) {
            /** @var mixed $config */
            $config = $globalConfig['asset_manager']['caching'];
        }

        return new AssetCacheManager(container: \Jield\Stdlib\Value::instance($container, \Laminas\ServiceManager\ServiceLocatorInterface::class), config: \Jield\Stdlib\Value::array($config));
    }
}
