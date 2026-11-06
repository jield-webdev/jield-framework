<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Jield\AssetManager\Resolver\ConcatResolver;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class ConcatResolverServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConcatResolver
    {
        /** @var mixed $config */
        $config = $container->get('config');
        $files = [];

        if (isset($config['asset_manager']['resolver_configs']['concat'])) {
            /** @var mixed $files */
            $files = $config['asset_manager']['resolver_configs']['concat'];
        }

        return new ConcatResolver(concats: \Jield\Stdlib\Value::iterable($files));
    }
}
