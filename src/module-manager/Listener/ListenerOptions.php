<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Laminas\Stdlib\AbstractOptions;
use Traversable;

use function gettype;
use function is_array;
use function rtrim;
use function sprintf;

/**
 * @extends \Laminas\Stdlib\AbstractOptions<mixed>
 */
class ListenerOptions extends AbstractOptions
{
    protected array $modulePaths = [];

    protected array $configGlobPaths = [];

    protected array $configStaticPaths = [];

    protected array $extraConfig = [];

    protected bool $configCacheEnabled = false;

    protected string $configCacheKey;

    protected ?string $cacheDir = null;

    protected bool $checkDependencies = true;

    protected bool $moduleMapCacheEnabled = false;

    protected string $moduleMapCacheKey;

    protected bool $useModuleLoader = true;

    public function getModulePaths(): array
    {
        return $this->modulePaths;
    }

    /**
     * Set an array of paths where modules reside
     *
     * @param Traversable|array $modulePaths
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setModulePaths(Traversable|array $modulePaths): static
    {
        

        $this->modulePaths = is_array($modulePaths) ? $modulePaths : iterator_to_array($modulePaths);
        return $this;
    }

    /**
     * Get the glob patterns to load additional config files
     *
     * @return array
     */
    public function getConfigGlobPaths(): array
    {
        return $this->configGlobPaths;
    }

    /**
     * Get the static paths to load additional config files
     *
     * @return array
     */
    public function getConfigStaticPaths(): array
    {
        return $this->configStaticPaths;
    }

    /**
     * Set the glob patterns to use for loading additional config files
     *
     * @param Traversable|array $configGlobPaths
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setConfigGlobPaths(Traversable|array $configGlobPaths): static
    {
        

        $this->configGlobPaths = is_array($configGlobPaths) ? $configGlobPaths : iterator_to_array($configGlobPaths);
        return $this;
    }

    /**
     * Set the static paths to use for loading additional config files
     *
     * @param Traversable|array $configStaticPaths
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setConfigStaticPaths(Traversable|array $configStaticPaths): static
    {
        

        $this->configStaticPaths = is_array($configStaticPaths) ? $configStaticPaths : iterator_to_array($configStaticPaths);
        return $this;
    }

    /**
     * Get any extra config to merge in.
     *
     * @return array|Traversable
     */
    public function getExtraConfig(): Traversable|array
    {
        return $this->extraConfig;
    }

    /**
     * Add some extra config array to the main config. This is mainly useful
     * for unit testing purposes.
     *
     * @param Traversable|array $extraConfig
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setExtraConfig(Traversable|array $extraConfig): static
    {
        

        $this->extraConfig = is_array($extraConfig) ? $extraConfig : iterator_to_array($extraConfig);
        return $this;
    }

    /**
     * Check if the config cache is enabled
     *
     * @return bool
     */
    public function getConfigCacheEnabled(): bool
    {
        return $this->configCacheEnabled;
    }

    /**
     * Set if the config cache should be enabled or not
     *
     * @param bool $enabled
     * @return static
     */
    public function setConfigCacheEnabled(bool $enabled): static
    {
        $this->configCacheEnabled = $enabled;
        return $this;
    }

    /**
     * Get key used to create the cache file name
     *
     * @return string
     */
    public function getConfigCacheKey(): string
    {
        return $this->configCacheKey;
    }

    /**
     * Set key used to create the cache file name
     *
     * @param string $configCacheKey the value to be set
     * @return static
     */
    public function setConfigCacheKey(string $configCacheKey): static
    {
        $this->configCacheKey = $configCacheKey;
        return $this;
    }

    /**
     * Get the path to the config cache
     *
     * Should this be an option, or should the dir option include the
     * filename, or should it simply remain hard-coded? Thoughts?
     *
     * @return string
     */
    public function getConfigCacheFile(): string
    {
        if ($this->getConfigCacheKey()) {
            return (string) ($this->getCacheDir()) . '/module-config-cache.' . $this->getConfigCacheKey() . '.php';
        }

        return (string) ($this->getCacheDir()) . '/module-config-cache.php';
    }

    /**
     * Get the path where cache file(s) are stored
     *
     * @return string|null
     */
    public function getCacheDir(): ?string
    {
        return $this->cacheDir;
    }

    /**
     * Set the path where cache files can be stored
     *
     * @param string|null $cacheDir the value to be set
     * @return static
     */
    public function setCacheDir(?string $cacheDir): static
    {
        $this->cacheDir = $cacheDir ? static::normalizePath(path: $cacheDir) : null;

        return $this;
    }

    /**
     * Check if the module class map cache is enabled
     *
     * @return bool
     */
    public function getModuleMapCacheEnabled(): bool
    {
        return $this->moduleMapCacheEnabled;
    }

    /**
     * Set if the module class map cache should be enabled or not
     *
     * @param bool $enabled
     * @return static
     */
    public function setModuleMapCacheEnabled(bool $enabled): static
    {
        $this->moduleMapCacheEnabled = $enabled;
        return $this;
    }

    /**
     * Get key used to create the cache file name
     *
     * @return string
     */
    public function getModuleMapCacheKey(): string
    {
        return $this->moduleMapCacheKey;
    }

    /**
     * Set key used to create the cache file name
     *
     * @param string $moduleMapCacheKey the value to be set
     * @return static
     */
    public function setModuleMapCacheKey(string $moduleMapCacheKey): static
    {
        $this->moduleMapCacheKey = $moduleMapCacheKey;
        return $this;
    }

    /**
     * Get the path to the module class map cache
     *
     * @return string
     */
    public function getModuleMapCacheFile(): string
    {
        if ($this->getModuleMapCacheKey()) {
            return (string) ($this->getCacheDir()) . '/module-classmap-cache.' . $this->getModuleMapCacheKey() . '.php';
        }

        return (string) ($this->getCacheDir()) . '/module-classmap-cache.php';
    }

    /**
     * Set whether to check dependencies during module loading or not
     *
     * @return bool
     */
    public function getCheckDependencies(): bool
    {
        return $this->checkDependencies;
    }

    /**
     * Set whether to check dependencies during module loading or not
     *
     * @param bool $checkDependencies the value to be set
     * @return static
     */
    public function setCheckDependencies(bool $checkDependencies): static
    {
        $this->checkDependencies = $checkDependencies;

        return $this;
    }

    /**
     * Whether or not to use jield-loader to autoload modules.
     *
     * @return bool
     */
    public function useLaminasLoader(): bool
    {
        return $this->useModuleLoader;
    }

    /**
     * Set a flag indicating if the module manager should use jield-loader
     *
     * Setting this option to false will disable ModuleAutoloader, requiring
     * other means of autoloading to be used (e.g., Composer).
     *
     * If disabled, the AutoloaderProvider feature will be disabled as well
     *
     * @param bool $flag
     * @return static
     */
    public function setuseModuleLoader(bool $flag): static
    {
        $this->useModuleLoader = $flag;
        return $this;
    }

    /**
     * Normalize a path for insertion in the stack
     *
     * @param string $path
     * @return string
     */
    public static function normalizePath(string $path): string
    {
        $path = rtrim(string: $path, characters: '/');
        return rtrim(string: $path, characters: '\\');
    }
}
