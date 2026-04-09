<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Jield\Authorize\Config;

/**
 * Factory for building a cache key generator
 */
class CacheKeyGeneratorFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): \Closure
    {
        $config   = $container->get(Config::class);
        $cacheKey = empty($config['cache_key']) ? 'bjyauthorize_acl' : (string)$config['cache_key'];

        return static fn() => $cacheKey;
    }
}
