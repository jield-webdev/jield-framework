<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Jield\AssetManager\Resolver\PathStackResolver;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class PathStackResolverServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): PathStackResolver
    {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        $pathStackResolver = new PathStackResolver();
        $paths = [];

        if (($config['asset_manager']['resolver_configs']['paths'] ?? null) !== null) {
            /** @mago-var mixed $paths */
            $paths = $config['asset_manager']['resolver_configs']['paths'];
        }

        $pathStackResolver->addPaths(paths: \Webmozart\Assert\Assert::isIterable($paths));

        return $pathStackResolver;
    }
}
