<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\Metadata;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\Hydrator\HydratorPluginManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webmozart\Assert\Assert;

use function is_array;

class MetadataMapFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Metadata\MetadataMap
    {
        /** @mago-var mixed $config */
        $config = $container->get('Jield\ApiTools\Hal\HalConfig');

        /** @mago-var mixed $hydrators */
        $hydrators = $container->has('HydratorManager')
            ? $container->get('HydratorManager')
            : new HydratorPluginManager(creationContext: $container);

        $map = isset($config['metadata_map']) && is_array(value: $config['metadata_map'])
            ? $config['metadata_map']
            : [];

        return new Metadata\MetadataMap(map: $map, hydrators: Assert::isInstanceOf(
            $hydrators,
            HydratorPluginManagerInterface::class,
        ));
    }
}
