<?php

declare(strict_types=1);

namespace Jield\AssetManager\Resolver;

use Assetic\Contracts\Asset\AssetInterface;
use Assetic\Factory\Resource\DirectoryResource;
use Jield\AssetManager\Asset\FileAsset;
use Jield\AssetManager\Exception;
use Jield\AssetManager\Service\MimeResolver;
use Jield\Stdlib\Value;
use Laminas\Stdlib\SplStack;
use Override;
use SplFileInfo;
use Webmozart\Assert\Assert;

/**
 * This resolver allows you to resolve from a stack of paths.
 */
class PathStackResolver implements ResolverInterface, MimeResolverAwareInterface
{
    protected SplStack $paths;

    /**
     * Flag indicating whether or not LFI protection for rendering view scripts is enabled
     */
    protected bool $lfiProtectionOn = true;

    /**
     * The mime resolver.
     */
    protected MimeResolver $mimeResolver;

    public function __construct()
    {
        $this->paths = new SplStack();
    }

    /**
     * Set the mime resolver
     */
    #[Override]
    public function setMimeResolver(MimeResolver $mimeResolver): void
    {
        $this->mimeResolver = $mimeResolver;
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
     * Add many paths to the stack at once
     */
    public function addPaths(iterable $paths): void
    {
        /** @mago-var mixed $path */
        foreach ($paths as $path) {
            $this->addPath(path: Assert::string($path));
        }
    }

    /**
     * Rest the path stack to the paths provided
     *
     * @throws Exception\InvalidArgumentException
     */
    public function setPaths(iterable $paths): void
    {
        $this->clearPaths();
        $this->addPaths(paths: $paths);
    }

    /**
     * Normalize a path for insertion in the stack
     */
    protected function normalizePath(string $path): string
    {
        $path = rtrim(string: $path, characters: '/\\');
        $path .= DIRECTORY_SEPARATOR;

        return $path;
    }

    /**
     * Add a single path to the stack
     *
     * @throws Exception\InvalidArgumentException
     */
    public function addPath(string $path): void
    {
        $this->paths[] = $this->normalizePath(path: $path);
    }

    /**
     * Clear all paths
     */
    public function clearPaths(): void
    {
        $this->paths = new SplStack();
    }

    /**
     * Returns stack of paths
     */
    public function getPaths(): SplStack
    {
        return $this->paths;
    }

    /**
     * Set LFI protection flag
     */
    public function setLfiProtection(bool $flag): PathStackResolver
    {
        $this->lfiProtectionOn = $flag;

        return $this;
    }

    /**
     * Return status of LFI protection flag
     */
    public function isLfiProtectionOn(): bool
    {
        return $this->lfiProtectionOn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function resolve(string $fileName): FileAsset|AssetInterface|null
    {
        if ($this->isLfiProtectionOn() && preg_match(pattern: '#\.\.[\\\/]#', subject: $fileName)) {
            return null;
        }

        /** @mago-var mixed $path */
        foreach ($this->getPaths() as $path) {
            $file = new SplFileInfo(filename: Value::stringify($path) . $fileName);

            if ($file->isReadable() && !$file->isDir()) {
                $filePath = $file->getRealPath();
                $mimeType = $this->getMimeResolver()->getMimeType(filename: Assert::string($filePath));
                $asset = new FileAsset(source: Assert::string($filePath));

                $asset->mimetype = $mimeType;

                return $asset;
            }
        }

        return null;
    }

    public function collect(): array
    {
        $collection = [];
        /** @mago-var mixed $path */
        foreach ($this->getPaths() as $path) {
            $locations = new SplStack();
            $pathInfo = new SplFileInfo(filename: Assert::string($path));
            $locations->push(value: $pathInfo);
            $basePath = $this->normalizePath(path: Assert::string($pathInfo->getRealPath()));

            while (!$locations->isEmpty()) {
                /** @var SplFileInfo $pathInfo */
                $pathInfo = $locations->pop();
                if (!$pathInfo->isReadable()) {
                    continue;
                }

                if ($pathInfo->isDir()) {
                    $dir = new DirectoryResource(path: Assert::string($pathInfo->getRealPath()));
                    /** @mago-var mixed $resource */
                    foreach ($dir as $resource) {
                        $locations->push(value: new SplFileInfo(filename: Assert::string($resource)));
                    }
                } else {
                    $relativePath = substr(
                        string: Assert::string($pathInfo->getRealPath()),
                        offset: strlen(string: $basePath),
                    );
                    if (!in_array($relativePath, $collection, true)) {
                        $collection[] = $relativePath;
                    }
                }
            }
        }

        return $collection;
    }
}
