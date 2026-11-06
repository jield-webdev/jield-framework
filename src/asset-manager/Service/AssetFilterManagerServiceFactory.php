<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

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
        /** @var mixed $config */
        $config = $container->get('config');

        if (!empty($config['asset_manager']['filters'])) {
            /** @var mixed $filters */
            $filters = $config['asset_manager']['filters'];
        }

        $assetFilterManager = new AssetFilterManager(config: \Jield\Stdlib\Value::array($filters));

        $assetFilterManager->setContainer(container: $container);
        $assetFilterManager->setMimeResolver(mimeResolver: \Jield\Stdlib\Service::get($container, MimeResolver::class));

        return $assetFilterManager;
    }
}
