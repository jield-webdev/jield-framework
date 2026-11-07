<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Jield\AssetManager\Resolver\MapResolver;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class MapResolverServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MapResolver
    {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        $map = [];

        if (isset($config['asset_manager']['resolver_configs']['map'])) {
            /** @mago-var mixed $map */
            $map = $config['asset_manager']['resolver_configs']['map'];
        }

        return new MapResolver(map: \Webmozart\Assert\Assert::isIterable($map));
    }
}
