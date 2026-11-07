<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Assetic\Contracts\Asset\AssetInterface;
use Jield\AssetManager\Exception;
use Jield\AssetManager\Resolver\ResolverInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\Response;
use Laminas\Stdlib\RequestInterface;
use Override;

/**
 * @category    AssetManager
 * @package     AssetManager
 */
class AssetManager implements AssetFilterManagerAwareInterface, AssetCacheManagerAwareInterface
{
    protected ResolverInterface $resolver;

    protected AssetFilterManager $filterManager;

    protected AssetCacheManager $cacheManager;

    protected ?AssetInterface $asset = null;

    protected string $path;

    /**
     * @var array The asset_manager configuration
     */
    protected array $config;

    /**
     * @var bool Whether this instance has at least one asset successfully set on response
     */
    protected bool $assetSetOnResponse = false;

    public function __construct(ResolverInterface $resolver, array $config = [])
    {
        $this->setResolver(resolver: $resolver);
        $this->setConfig(config: $config);
    }

    /**
     * Set the config
     */
    protected function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Check if the request resolves to an asset.
     */
    public function resolvesToAsset(RequestInterface $request): bool
    {
        $this->asset ??= $this->resolve(request: $request);

        return (bool) $this->asset;
    }

    /**
     * Returns true if this instance of asset manager has at least one asset successfully set on response
     */
    public function assetSetOnResponse(): bool
    {
        return $this->assetSetOnResponse;
    }

    /**
     * Set the resolver to use in the asset manager
     */
    public function setResolver(ResolverInterface $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * Get the resolver used by the asset manager
     */
    public function getResolver(): ResolverInterface
    {
        return $this->resolver;
    }

    /**
     * Set the asset on the response, including headers and content.
     *
     * @throws   Exception\RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function setAssetOnResponse(Response $response): Response
    {
        if (!$this->asset instanceof \Jield\AssetManager\Asset\AssetInterface) {
            throw new Exception\RuntimeException(
                message: 'Unable to set asset on response. Request has not been resolved to an asset.',
            );
        }

        if (empty($this->asset->getMimeType())) {
            throw new Exception\RuntimeException(message: 'Expected property "mimetype" on asset.');
        }

        $this->getAssetFilterManager()->setFilters(path: $this->path, asset: \Webmozart\Assert\Assert::isInstanceOf($this->asset, \Jield\AssetManager\Asset\AssetInterface::class));

        $this->asset = $this->getAssetCacheManager()->setCache(path: $this->path, asset: \Webmozart\Assert\Assert::isInstanceOf($this->asset, \Jield\AssetManager\Asset\AssetInterface::class));
        $mimeType = $this->asset->getMimetype();
        $assetContents = $this->asset->dump();
        // @codeCoverageIgnoreStart
        $contentLength = function_exists(function: 'mb_strlen')
            ? mb_strlen($assetContents, '8bit')
            : strlen(string: $assetContents);

        // @codeCoverageIgnoreEnd
        // Only clean the output buffer if it's turned on and something
        // has been buffered.
        if (!empty($this->config['clear_output_buffer']) && (ob_get_length() ?: 0) > 0) {
            ob_clean();
        }

        $response
            ->getHeaders()
            ->addHeaderLine(headerFieldNameOrLine: 'Content-Transfer-Encoding', fieldValue: 'binary')
            ->addHeaderLine(headerFieldNameOrLine: 'Content-Type', fieldValue: $mimeType)
            ->addHeaderLine(headerFieldNameOrLine: 'Content-Length', fieldValue: (string) $contentLength);

        $response->setContent(value: $assetContents);

        $this->assetSetOnResponse = true;

        return $response;
    }

    /**
     * Resolve the request to a file.
     *
     *
     * @return ?AssetInterface AssetInterface when not found, AssetInterface when resolved.
     */
    protected function resolve(RequestInterface $request): ?AssetInterface
    {
        if (!$request instanceof Request) {
            return null;
        }

        /* @var $request Request */
        /* @var $uri UriInterface */
        $uri = $request->getUri();
        $fullPath = $uri->getPath();
        $path = substr(string: (string) $fullPath, offset: strlen(string: $request->getBasePath()) + 1);

        //Remove any trailing slash from the path
        $path = rtrim(string: $path, characters: '/');

        $this->path = $path;

        $asset = $this->getResolver()->resolve(fileName: $path);

        if (!$asset instanceof AssetInterface) {
            return null;
        }

        return $asset;
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
     * Get the AssetFilterManager
     */
    #[Override]
    public function getAssetFilterManager(): AssetFilterManager
    {
        return $this->filterManager;
    }

    /**
     * Set the AssetCacheManager.
     */
    #[Override]
    public function setAssetCacheManager(AssetCacheManager $cacheManager): void
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Get the AssetCacheManager
     */
    #[Override]
    public function getAssetCacheManager(): AssetCacheManager
    {
        return $this->cacheManager;
    }
}
