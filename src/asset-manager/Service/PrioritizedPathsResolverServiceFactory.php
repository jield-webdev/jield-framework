<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Jield\AssetManager\Resolver\PrioritizedPathsResolver;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class PrioritizedPathsResolverServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): PrioritizedPathsResolver {
        $config = $container->get('config');
        $prioritizedPathsResolver = new PrioritizedPathsResolver();
        /** @var mixed $paths */
        $paths = $config['asset_manager']['resolver_configs']['prioritized_paths'] ?? [];
        $prioritizedPathsResolver->addPaths(paths: Value::iterable($paths));

        return $prioritizedPathsResolver;
    }
}
