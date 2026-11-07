<?php

declare(strict_types=1);

namespace Jield\AssetManager\Resolver;

use Jield\AssetManager\Asset\AggregateAsset;
use Jield\AssetManager\Asset\AssetInterface;
use Jield\AssetManager\Exception;
use Jield\AssetManager\Service\AssetFilterManager;
use Jield\AssetManager\Service\AssetFilterManagerAwareInterface;
use Jield\AssetManager\Service\MimeResolver;
use Jield\Stdlib\Value;
use Laminas\Stdlib\ArrayUtils;
use Override;
use Webmozart\Assert\Assert;

/**
 * This resolver allows the resolving of concatenated files.
 * Concatted files are added as an StringAsset and filters get applied to concatenated string.
 */
class ConcatResolver implements
    ResolverInterface,
    AggregateResolverAwareInterface,
    AssetFilterManagerAwareInterface,
    MimeResolverAwareInterface
{
    /**
     * @var null|ResolverInterface
     */
    protected ?ResolverInterface $aggregateResolver = null;

    /**
     * @var null|AssetFilterManager The filterManager service.
     */
    protected ?AssetFilterManager $filterManager = null;

    /**
     * @var array the concats
     */
    protected array $concats = [];

    /**
     * @var MimeResolver The mime resolver.
     */
    protected MimeResolver $mimeResolver;

    /**
     * Constructor
     *
     * Instantiate and optionally populate concats.
     *
     * @param iterable $concats
     */
    public function __construct(iterable $concats = [])
    {
        $this->setConcats(concats: $concats);
    }

    /**
     * Set the mime resolver
     *
     * @param MimeResolver $mimeResolver
     */
    #[Override]
    public function setMimeResolver(MimeResolver $mimeResolver): void
    {
        $this->mimeResolver = $mimeResolver;
    }

    /**
     * Get the mime resolver
     *
     * @return MimeResolver
     */
    #[Override]
    public function getMimeResolver(): MimeResolver
    {
        return $this->mimeResolver;
    }

    /**
     * Set (overwrite) concats
     *
     * Concats should be arrays or Traversable objects with name => path pairs
     *
     * @param iterable $concats
     * @throws Exception\InvalidArgumentException
     */
    public function setConcats(iterable $concats): void
    {
        $this->concats = ArrayUtils::iteratorToArray(iterator: $concats);
    }

    /**
     * Set the aggregate resolver.
     *
     * @param ResolverInterface $aggregateResolver
     */
    #[Override]
    public function setAggregateResolver(ResolverInterface $aggregateResolver): void
    {
        $this->aggregateResolver = $aggregateResolver;
    }

    /**
     * Get the aggregate resolver.
     */
    #[Override]
    public function getAggregateResolver(): ResolverInterface
    {
        return Assert::isInstanceOf($this->aggregateResolver, \Jield\AssetManager\Resolver\ResolverInterface::class);
    }

    /**
     * Retrieve the concats
     */
    public function getConcats(): array
    {
        return $this->concats;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function resolve(string $fileName): ?\Assetic\Contracts\Asset\AssetInterface
    {
        if (!isset($this->concats[$fileName])) {
            return null;
        }

        $resolvedAssets = [];

        /** @mago-var mixed $assetName */
        foreach (Value::toArray($this->concats[$fileName]) as $assetName) {
            $resolvedAsset = $this->getAggregateResolver()->resolve(fileName: (string) $assetName);

            if (!$resolvedAsset instanceof AssetInterface) {
                throw new Exception\RuntimeException(message: sprintf(
                    'Asset "%s" from collection "%s" can\'t be resolved '
                    . 'to an Asset implementing \Jield\AssetManager\Asset\AssetInterface.',
                    Value::scalar($assetName),
                    $fileName,
                ));
            }

            $resolvedAsset->setMimeType($this->getMimeResolver()->getMimeType(
                filename: (string) $resolvedAsset->getSourceRoot() . (string) $resolvedAsset->getSourcePath(),
            ));

            $this->getAssetFilterManager()->setFilters(path: Assert::string($assetName), asset: $resolvedAsset);

            $resolvedAssets[] = $resolvedAsset;
        }

        $aggregateAsset = new AggregateAsset(content: $resolvedAssets);
        $this->getAssetFilterManager()->setFilters(path: $fileName, asset: $aggregateAsset);
        $aggregateAsset->setTargetPath(targetPath: $fileName);

        return $aggregateAsset;
    }

    /**
     * Set the AssetFilterManager.
     *
     * @param AssetFilterManager $filterManager
     */
    #[Override]
    public function setAssetFilterManager(AssetFilterManager $filterManager): void
    {
        $this->filterManager = $filterManager;
    }

    /**
     * Get the AssetFilterManager
     */
    #[Override]
    public function getAssetFilterManager(): AssetFilterManager
    {
        return Assert::isInstanceOf($this->filterManager, \Jield\AssetManager\Service\AssetFilterManager::class);
    }

    public function collect(): array
    {
        return array_keys(array: $this->concats);
    }
}
