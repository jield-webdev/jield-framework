<?php

declare(strict_types=1);

namespace Jield\AssetManager\Resolver;

use Assetic\Factory\Resource\DirectoryResource;
use Jield\AssetManager\Asset\FileAsset;
use Jield\AssetManager\Exception;
use Jield\AssetManager\Service\MimeResolver;
use Jield\Stdlib\Value;
use Laminas\Stdlib\SplStack;
use Override;
use RuntimeException;
use SplFileInfo;
use Webmozart\Assert\Assert;

/**
 * This resolver allows you to resolve from a stack of aliases to a path.
 */
class AliasPathStackResolver implements ResolverInterface, MimeResolverAwareInterface
{
    protected array $aliases = [];

    /**
     * Flag indicating whether or not LFI protection for rendering view scripts is enabled
     */
    protected bool $lfiProtectionOn = true;

    /**
     * The mime resolver.
     *
     * @var MimeResolver
     */
    protected MimeResolver $mimeResolver;

    /**
     * Constructor
     *
     * Populate the array stack with a list of aliases and their corresponding paths
     *
     * @param array $aliases
     * @throws Exception\InvalidArgumentException
     */
    public function __construct(array $aliases)
    {
        /** @mago-var mixed $path */
        foreach ($aliases as $alias => $path) {
            $this->addAlias(alias: Assert::string($alias), path: Assert::string($path));
        }
    }

    /**
     * Add a single alias to the stack
     *
     * @param string $alias
     * @param string $path
     * @throws Exception\InvalidArgumentException
     */
    private function addAlias(string $alias, string $path): void
    {
        $this->aliases[$alias] = $this->normalizePath(path: $path);
    }

    /**
     * Normalize a path for insertion in the stack
     *
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        return rtrim(string: $path, characters: '/\\') . DIRECTORY_SEPARATOR;
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
     * Set LFI protection flag
     *
     * @param bool $flag
     * @return self
     */
    public function setLfiProtection(bool $flag): AliasPathStackResolver
    {
        $this->lfiProtectionOn = $flag;

        return $this;
    }

    /**
     * Return status of LFI protection flag
     *
     * @return bool
     */
    public function isLfiProtectionOn(): bool
    {
        return $this->lfiProtectionOn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function resolve(string $fileName): ?FileAsset
    {
        if ($this->isLfiProtectionOn() && preg_match(pattern: '#\.\.[\\\/]#', subject: $fileName)) {
            return null;
        }

        /** @mago-var mixed $path */
        foreach ($this->aliases as $alias => $path) {
            if (!str_contains(haystack: $fileName, needle: Assert::string($alias))) {
                continue;
            }

            $correctedFilename = substr_replace(
                string: $fileName,
                replace: '',
                offset: 0,
                length: strlen(string: Assert::string($alias)),
            );

            $file = new SplFileInfo(filename: Value::stringify($path) . $correctedFilename);

            if ($file->isReadable() && !$file->isDir()) {
                $filePath = $file->getRealPath();
                $mimeType = $this->getMimeResolver()->getMimeType(filename: Assert::string($filePath));
                $asset = new FileAsset(source: Assert::string($filePath));

                $asset->setMimetype(mimetype: $mimeType);

                return $asset;
            }
        }

        return null;
    }

    public function collect(): array
    {
        $collection = [];

        /** @mago-var mixed $path */
        foreach ($this->aliases as $alias => $path) {
            $locations = new SplStack();
            $pathInfo = new SplFileInfo(filename: Assert::string($path));
            $locations->push(value: $pathInfo);
            $basePath = $this->normalizePath(path: Assert::string($pathInfo->getRealPath()));

            while (!$locations->isEmpty()) {
                /** @var SplFileInfo $pathInfo */
                $pathInfo = $locations->pop();
                if (!$pathInfo->isReadable()) {
                    throw new RuntimeException(message: sprintf('%s is not readable.', $pathInfo->getPath()));
                }

                if ($pathInfo->isDir()) {
                    /** @mago-var mixed $resource */
                    foreach (new DirectoryResource(path: Assert::string($pathInfo->getRealPath())) as $resource) {
                        $locations->push(value: new SplFileInfo(filename: Assert::string($resource)));
                    }
                } else {
                    $collection[] =
                        $alias
                        . substr(string: Assert::string($pathInfo->getRealPath()), offset: strlen(string: $basePath));
                }
            }
        }

        return array_unique(array: $collection);
    }
}
