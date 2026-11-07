<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

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
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): StorageInterface
    {
        $storageFactory = Service::get($container, StorageAdapterFactoryInterface::class);

        $cacheOptions = Assert::isArray($container->get(Config::class))['cache_options'];

        $plugins = [];
        foreach (Assert::isIterable($cacheOptions['plugins']) as $plugin) {
            $plugins[] = is_array(value: $plugin)
                ? $plugin
                : [
                    'name' => $plugin,
                ];
        }

        $configuration = [
            'adapter' => Assert::stringNotEmpty(Value::path($cacheOptions, ['adapter', 'name'])),
            'options' => $cacheOptions['options'] ?? [],
            'plugins' => $plugins,
        ];
        $storageFactory->assertValidConfigurationStructure($configuration);

        return $storageFactory->createFromArrayConfiguration($configuration);
    }
}
