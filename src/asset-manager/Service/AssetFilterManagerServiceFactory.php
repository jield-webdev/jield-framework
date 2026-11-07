<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class AssetFilterManagerServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AssetFilterManager
    {
        $filters = [];
        /** @mago-var mixed $config */
        $config = $container->get('config');

        if (!!($config['asset_manager']['filters'] ?? null)) {
            /** @mago-var mixed $filters */
            $filters = $config['asset_manager']['filters'] ?? null;
        }

        $assetFilterManager = new AssetFilterManager(config: \Webmozart\Assert\Assert::isArray($filters));

        $assetFilterManager->setContainer(container: $container);
        $assetFilterManager->setMimeResolver(mimeResolver: Service::get($container, MimeResolver::class));

        return $assetFilterManager;
    }
}
