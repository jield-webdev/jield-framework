<?php

declare(strict_types=1);

namespace Jield\AssetManager\View\Helper;

use Jield\AssetManager\Resolver\ResolverInterface;
use Jield\Mvc\View\Helper\AbstractHelper;
use Laminas\Cache\Storage\Adapter\AbstractAdapter as AbstractCacheAdapter;

class Asset extends AbstractHelper
{
    public function __construct(
        private readonly ResolverInterface $assetManagerResolver,
        private readonly ?AbstractCacheAdapter $cache,
        private readonly array $config,
    ) {}

    /**
     * Append timestamp as query param to the filename
     */
    private function appendTimestamp(string $filename, string $queryString, ?int $timestamp = null): string
    {
        // current timestamp as default
        $timestamp ??= time();

        return $filename . '?' . urlencode(string: $queryString) . '=' . $timestamp;
    }

    /**
     * find the file and if it exists, append its unix modification time to the filename
     */
    private function elaborateFilePath(string $filename, string $queryString): string
    {
        $asset = $this->assetManagerResolver->resolve(fileName: $filename);
        if ($asset !== null) {
            // append last modified date to the filepath and use a custom query string
            return $this->appendTimestamp(
                filename: $filename,
                queryString: $queryString,
                timestamp: $asset->getLastModified(),
            );
        }

        return $filename;
    }

    /**
     * Use the cache to get the filePath
     *
     * @throws \Laminas\Cache\Exception\ExceptionInterface
     */
    private function getFilePathFromCache(string $filename, string $queryString): mixed
    {
        // return if cache not found
        $cache = $this->cache;
        if ($cache === null) {
            return null;
        }

        // cache key based on the filename
        $cacheKey = md5(string: $filename);
        $itemIsFoundInCache = false;
        /** @mago-var mixed $filePath */
        $filePath = $cache->getItem($cacheKey, $itemIsFoundInCache);

        // if there is no element in the cache, elaborate and cache it
        if ($itemIsFoundInCache === false || $filePath === null) {
            $filePath = $this->elaborateFilePath(filename: $filename, queryString: $queryString);
            $cache->setItem($cacheKey, $filePath);
        }

        return $filePath;
    }

    /**
     * Output the filepath with its unix modification time as query param
     *
     * @throws \Laminas\Cache\Exception\ExceptionInterface
     */
    public function __invoke(string $filename): string
    {
        // nothing to append
        if (empty($this->config['view_helper']['append_timestamp'])) {
            return $filename;
        }

        // search the cache config for the specific file requested (if none, use the default one)
        if (isset($this->config['caching'][$filename])) {
            /** @mago-var mixed $cacheConfig */
            $cacheConfig = $this->config['caching'][$filename];
        } elseif (isset($this->config['caching']['default'])) {
            /** @mago-var mixed $cacheConfig */
            $cacheConfig = $this->config['caching']['default'];
        }

        // query string params
        /** @mago-var mixed $queryString */
        $queryString = $this->config['view_helper']['query_string'] ?? '_';

        // no cache dir is defined
        if (!isset($cacheConfig['options']['dir'])) {
            // append current timestamp to the filepath and use a custom query string
            return $this->appendTimestamp(filename: $filename, queryString: \Webmozart\Assert\Assert::string($queryString));
        }

        // get the filePath from the cache (if available)
        /** @mago-var mixed $filePath */
        $filePath = $this->getFilePathFromCache(filename: $filename, queryString: \Webmozart\Assert\Assert::string($queryString));
        if ($filePath !== null) {
            return \Webmozart\Assert\Assert::string($filePath);
        }

        return $this->elaborateFilePath(filename: $filename, queryString: \Webmozart\Assert\Assert::string($queryString));
    }
}
