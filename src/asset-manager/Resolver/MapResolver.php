<?php

declare(strict_types=1);

namespace Jield\AssetManager\Resolver;

use Jield\AssetManager\Asset\AssetInterface;
use Jield\AssetManager\Asset\FileAsset;
use Jield\AssetManager\Asset\HttpAsset;
use Jield\AssetManager\Exception;
use Jield\AssetManager\Service\MimeResolver;
use Laminas\Stdlib\ArrayUtils;
use Override;
use Traversable;
use Webmozart\Assert\Assert;

/**
 * This resolver allows you to resolve using a 1 on 1 mapping to a file.
 */
class MapResolver implements ResolverInterface, MimeResolverAwareInterface
{
    protected array $map = [];

    /**
     * @var MimeResolver The mime resolver.
     */
    protected MimeResolver $mimeResolver;

    /**
     * Instantiate and optionally populate map.
     */
    public function __construct(iterable $map = [])
    {
        $this->setMap(map: $map);
    }

    /**
     * Retrieve the map
     */
    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * Set (overwrite) map
     *
     * Maps should be arrays or Traversable objects with name => path pairs
     *
     * @throws Exception\InvalidArgumentException
     */
    public function setMap(iterable $map): void
    {
        if ($map instanceof Traversable) {
            $map = ArrayUtils::iteratorToArray(iterator: $map);
        }

        $this->map = $map;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function resolve(string $fileName): ?AssetInterface
    {
        if (!isset($this->map[$fileName])) {
            return null;
        }

        /** @mago-var mixed $file */
        $file = $this->map[$fileName];
        $mimeType = $this->getMimeResolver()->getMimeType(filename: Assert::string($file));

        if (false === filter_var(value: $file, filter: FILTER_VALIDATE_URL)) {
            $asset = new FileAsset(source: Assert::string($file));
        } else {
            $asset = new HttpAsset(sourceUrl: Assert::string($file));
        }

        $asset->setMimetype(mimetype: $mimeType);

        return $asset;
    }

    /**
     * Get the mime resolver
     */
    #[Override]
    public function getMimeResolver(): MimeResolver
    {
        return $this->mimeResolver;
    }

    /**
     * Set the mime resolver
     */
    #[Override]
    public function setMimeResolver(MimeResolver $mimeResolver): void
    {
        $this->mimeResolver = $mimeResolver;
    }

    public function collect(): array
    {
        return array_keys(array: $this->map);
    }
}
