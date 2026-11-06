<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

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
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): \Laminas\Cache\Storage\StorageInterface {
        
        $storageFactory = \Jield\Stdlib\Service::get($container, StorageAdapterFactoryInterface::class);

        /** @var mixed $cacheOptions */
        $cacheOptions = \Jield\Stdlib\Value::array($container->get(Config::class))['cache_options'];

        $plugins = [];
        /** @var mixed $plugin */
        foreach (\Jield\Stdlib\Value::iterable(\Jield\Stdlib\Value::accessible($cacheOptions)['plugins']) as $plugin) {
            $plugins[] = is_array(value: $plugin)
                ? $plugin
                : [
                    'name' => $plugin,
                ];
        }

        return $storageFactory->create(
            storage: \Jield\Stdlib\Value::nonEmptyString(\Jield\Stdlib\Value::path($cacheOptions, ['adapter', 'name'])),
            options: \Jield\Stdlib\Value::stringKeyedArray($cacheOptions['options'] ?? []),
            plugins: $plugins,
        );
    }
}
