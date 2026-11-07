<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Jield\AssetManager\Resolver\AliasPathStackResolver;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class AliasPathStackResolverServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): AliasPathStackResolver {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        $aliases = [];

        if (($config['asset_manager']['resolver_configs']['aliases'] ?? null) !== null) {
            /** @mago-var mixed $aliases */
            $aliases = $config['asset_manager']['resolver_configs']['aliases'];
        }

        return new AliasPathStackResolver(aliases: \Webmozart\Assert\Assert::isArray($aliases));
    }
}
