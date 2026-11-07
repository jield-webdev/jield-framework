<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\Metadata;
use Jield\Stdlib\Value;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\Hydrator\HydratorPluginManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function is_array;

class MetadataMapFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Metadata\MetadataMap
    {
        $config = $container->get('Jield\ApiTools\Hal\HalConfig');

        /** @var mixed $hydrators */
        $hydrators = $container->has('HydratorManager')
            ? $container->get('HydratorManager')
            : new HydratorPluginManager(creationContext: $container);

        $map = isset($config['metadata_map']) && is_array(value: $config['metadata_map'])
            ? $config['metadata_map']
            : [];

        return new Metadata\MetadataMap(map: $map, hydrators: Value::instance($hydrators, HydratorPluginManagerInterface::class));
    }
}
