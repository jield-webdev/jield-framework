<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Jield\Authorize\Config;
use function is_array;

/**
 * Factory for building the cache storage
 */
class CacheFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): \Laminas\Cache\Storage\StorageInterface
    {
        /** @var StorageAdapterFactoryInterface $storageFactory */
        $storageFactory = $container->get(StorageAdapterFactoryInterface::class);

        $cacheOptions = $container->get(Config::class)['cache_options'];

        $plugins = [];
        foreach ($cacheOptions['plugins'] as $plugin) {
            $plugins[] = is_array(value: $plugin) ? $plugin : [
                'name' => $plugin,
            ];
        }

        return $storageFactory->create(
            storage: $cacheOptions['adapter']['name'],
            options: $cacheOptions['options'] ?? [],
            plugins: $plugins
        );
    }
}
