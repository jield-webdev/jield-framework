<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Jield\AssetManager\Exception;
use Jield\AssetManager\Resolver\AggregateResolver;
use Jield\AssetManager\Resolver\AggregateResolverAwareInterface;
use Jield\AssetManager\Resolver\MimeResolverAwareInterface;
use Jield\AssetManager\Resolver\ResolverInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Factory class for AssetManagerService
 *
 * @category   AssetManager
 * @package    AssetManager
 */
class AggregateResolverServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AggregateResolver
    {
        /** @var mixed $config */
        $config = $container->get('config');
        /** @var mixed $config */
        $config = $config['asset_manager'] ?? [];

        $resolver = new AggregateResolver();

        if (empty($config['resolvers'])) {
            return $resolver;
        }

        /** @var mixed $resolverService
         * @var mixed $priority
         */
        foreach (\Jield\Stdlib\Value::iterable($config['resolvers']) as $resolverService => $priority) {
            /** @var mixed $resolverService */
            $resolverService = $container->get(\Jield\Stdlib\Value::string($resolverService));

            if (!$resolverService instanceof ResolverInterface) {
                throw new Exception\RuntimeException(
                    message: 'Service does not implement the required interface ResolverInterface.',
                );
            }

            if ($resolverService instanceof AggregateResolverAwareInterface) {
                $resolverService->setAggregateResolver(aggregateResolver: $resolver);
            }

            if ($resolverService instanceof MimeResolverAwareInterface) {
                $resolverService->setMimeResolver(mimeResolver: \Jield\Stdlib\Service::get($container, MimeResolver::class));
            }

            if ($resolverService instanceof AssetFilterManagerAwareInterface) {
                $resolverService->setAssetFilterManager(filterManager: \Jield\Stdlib\Service::get($container, AssetFilterManager::class));
            }

            $resolver->attach(resolver: $resolverService, priority: \Jield\Stdlib\Value::int($priority));
        }

        return $resolver;
    }
}
