<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Jield\AssetManager\Exception\InvalidArgumentException;
use Jield\AssetManager\View\Helper\Asset;
use Jield\Stdlib\Service;
use Laminas\Cache\Storage\Adapter\AbstractAdapter as AbstractCacheAdapter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Override;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class AssetViewHelperFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Asset
    {
        /** @mago-var mixed $config */
        $config = \Jield\Stdlib\Value::accessible($container->get('config'))['asset_manager'];

        $assetManagerResolver = Service::get($container, AssetManager::class)->getResolver();

        $cache = $this->loadCache(
            container: Assert::isInstanceOf($container, \Laminas\ServiceManager\ServiceLocatorInterface::class),
            config: Assert::isArray($config),
        );

        return new Asset(assetManagerResolver: $assetManagerResolver, cache: $cache, config: Assert::isArray($config));
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws InvalidArgumentException
     */
    private function loadCache(ContainerInterface $container, array $config): ?AbstractCacheAdapter
    {
        // check if the cache is configured
        if (!isset($config['view_helper']['cache'])) {
            return null;
        }

        // get the cache, if it's a string, search it among services
        /** @mago-var mixed $cache */
        $cache = $config['view_helper']['cache'];
        if (is_string(value: $cache)) {
            /** @mago-var mixed $cache */
            $cache = $container->get($cache);
        }

        // exception in case cache is not an Adapter that extend the AbstractAdapter of Laminas\Cache\Storage
        if ($cache !== null && !$cache instanceof AbstractCacheAdapter) {
            throw new InvalidArgumentException(message: 'Invalid cache provided, you must pass a Cache Adapter that extend
                Laminas\Cache\Storage\Adapter\AbstractAdapter');
        }

        return $cache;
    }
}
