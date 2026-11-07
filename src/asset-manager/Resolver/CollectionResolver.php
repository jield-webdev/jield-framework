<?php

declare(strict_types=1);

namespace Jield\AssetManager\Resolver;

use Jield\AssetManager\Asset\AssetCollection;
use Jield\AssetManager\Asset\AssetInterface;
use Jield\AssetManager\Exception;
use Jield\AssetManager\Service\AssetFilterManager;
use Jield\AssetManager\Service\AssetFilterManagerAwareInterface;
use Laminas\Stdlib\ArrayUtils;
use Override;
use Traversable;

/**
 * This resolver allows the resolving of collections.
 * Collections are strictly checked by mime-type,
 * and added to an AssetCollection when all checks passed.
 */
class CollectionResolver implements ResolverInterface, AggregateResolverAwareInterface, AssetFilterManagerAwareInterface
{
    protected ResolverInterface $aggregateResolver;

    protected AssetFilterManager $filterManager;

    protected array $collections = [];

    /**
     * Instantiate and optionally populate collections.
     */
    public function __construct(iterable $collections = [])
    {
        $this->setCollections(collections: $collections);
    }

    /**
     * Retrieve the collections
     */
    public function getCollections(): array
    {
        return $this->collections;
    }

    /**
     * Set (overwrite) collections
     *
     * Collections should be arrays or Traversable objects with name => path pairs
     *
     * @throws Exception\InvalidArgumentException
     */
    public function setCollections(iterable $collections): void
    {
        if ($collections instanceof Traversable) {
            $collections = ArrayUtils::iteratorToArray(iterator: $collections);
        }

        $this->collections = $collections;
    }

    /**
     * {@inheritDoc}
     * @throws Exception\RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    #[Override]
    public function resolve(string $fileName): ?\Assetic\Contracts\Asset\AssetInterface
    {
        if (($this->collections[$fileName] ?? null) === null) {
            return null;
        }

        if (!is_array(value: $this->collections[$fileName])) {
            throw new Exception\RuntimeException(message: sprintf(
                'Collection with name %s is not an an array.',
                $fileName,
            ));
        }

        //fix82 Extend from the local assetCollection with mimetype support
        $collection = new AssetCollection();
        $mimeType = null;
        $collection->setTargetPath(targetPath: $fileName);
        /** @mago-var mixed $asset */
        foreach ($this->collections[$fileName] as $asset) {
            if (!is_string(value: $asset)) {
                throw new Exception\RuntimeException(
                    message: 'Asset should be of type string. got ' . gettype(value: $asset),
                );
            }

            if (null === ($res = $this->getAggregateResolver()->resolve(fileName: $asset))) {
                throw new Exception\RuntimeException(message: sprintf("Asset '%s' could not be found.", $asset));
            }

            if (!$res instanceof AssetInterface) {
                throw new Exception\RuntimeException(message: sprintf(
                    "Asset '%s' does not implement Jield\\Jield\AssetManager\\Asset\\AssetInterface.",
                    $asset,
                ));
            }

            if (null !== $mimeType && $res->getMimeType() !== $mimeType) {
                throw new Exception\RuntimeException(message: sprintf(
                    'Asset "%s" from collection "%s" doesn\'t have the expected mime-type "%s".',
                    $asset,
                    $fileName,
                    $mimeType,
                ));
            }

            $mimeType = $res->getMimeType();

            $this->getAssetFilterManager()->setFilters(path: $asset, asset: $res);

            $collection->add(asset: $res);
        }

        $collection->setMimetype(mimetype: $mimeType);

        return $collection;
    }

    /**
     * Get the aggregate resolver.
     */
    #[Override]
    public function getAggregateResolver(): ResolverInterface
    {
        return $this->aggregateResolver;
    }

    /**
     * Set the aggregate resolver.
     */
    #[Override]
    public function setAggregateResolver(ResolverInterface $aggregateResolver): void
    {
        $this->aggregateResolver = $aggregateResolver;
    }

    /**
     * Get the AssetFilterManager
     */
    #[Override]
    public function getAssetFilterManager(): AssetFilterManager
    {
        return $this->filterManager;
    }

    /**
     * Set the AssetFilterManager.
     */
    #[Override]
    public function setAssetFilterManager(AssetFilterManager $filterManager): void
    {
        $this->filterManager = $filterManager;
    }

    /**
     * {}
     */
    public function collect(): array
    {
        return array_keys(array: $this->collections);
    }
}
