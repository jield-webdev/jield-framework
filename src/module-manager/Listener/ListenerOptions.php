<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Laminas\Stdlib\AbstractOptions;
use Traversable;

use function func_get_args;
use function gettype;
use function is_array;
use function rtrim;
use function sprintf;

class ListenerOptions extends AbstractOptions
{
    /** @var array */
    protected array $modulePaths = [];

    /** @var array */
    protected array $configGlobPaths = [];

    /** @var array */
    protected array $configStaticPaths = [];

    /** @var array */
    protected array $extraConfig = [];

    /** @var bool */
    protected bool $configCacheEnabled = false;

    /** @var string */
    protected string $configCacheKey;

    /** @var string|null */
    protected ?string $cacheDir;

    /** @var bool */
    protected bool $checkDependencies = true;

    /** @var bool */
    protected bool $moduleMapCacheEnabled = false;

    /** @var string */
    protected string $moduleMapCacheKey;

    /** @var bool */
    protected bool $useJieldLoader = true;

    /**
     * Get an array of paths where modules reside
     *
     * @return array
     */
    public function getModulePaths(): array
    {
        return $this->modulePaths;
    }

    /**
     * Set an array of paths where modules reside
     *
     * @param Traversable|array $modulePaths
     * @return ListenerOptions Provides fluent interface
     *@throws Exception\InvalidArgumentException
     */
    public function setModulePaths(Traversable|array $modulePaths): static
    {
        if (! is_array(value: $modulePaths) && ! $modulePaths instanceof Traversable) {
            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    'Argument passed to %s::%s() must be an array, '
                    . 'implement the Traversable interface, or be an '
                    . 'instance of Jield\Config\Config. %s given.',
                    self::class,
                    __METHOD__,
                    gettype(value: $modulePaths)
                )
            );
        }

        $this->modulePaths = $modulePaths;
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
     * @return ListenerOptions Provides fluent interface
     *@throws Exception\InvalidArgumentException
     */
    public function setConfigGlobPaths(Traversable|array $configGlobPaths): static
    {
        if (! is_array(value: $configGlobPaths) && ! $configGlobPaths instanceof Traversable) {
            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    'Argument passed to %s::%s() must be an array, '
                    . 'implement the Traversable interface, or be an '
                    . 'instance of Jield\Config\Config. %s given.',
                    self::class,
                    __METHOD__,
                    gettype(value: $configGlobPaths)
                )
            );
        }

        $this->configGlobPaths = $configGlobPaths;
        return $this;
    }

    /**
     * Set the static paths to use for loading additional config files
     *
     * @param Traversable|array $configStaticPaths
     * @return ListenerOptions Provides fluent interface
     *@throws Exception\InvalidArgumentException
     */
    public function setConfigStaticPaths(Traversable|array $configStaticPaths): static
    {
        if (! is_array(value: $configStaticPaths) && ! $configStaticPaths instanceof Traversable) {
            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    'Argument passed to %s::%s() must be an array, '
                    . 'implement the Traversable interface, or be an '
                    . 'instance of Jield\Config\Config. %s given.',
                    self::class,
                    __METHOD__,
                    gettype(value: $configStaticPaths)
                )
            );
        }

        $this->configStaticPaths = $configStaticPaths;
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
     * @return ListenerOptions Provides fluent interface
     *@throws Exception\InvalidArgumentException
     */
    public function setExtraConfig(Traversable|array $extraConfig): static
    {
        if (! is_array(value: $extraConfig) && ! $extraConfig instanceof Traversable) {
            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    'Argument passed to %s::%s() must be an array, '
                    . 'implement the Traversable interface, or be an '
                    . 'instance of Jield\Config\Config. %s given.',
                    self::class,
                    __METHOD__,
                    gettype(value: $extraConfig)
                )
            );
        }

        $this->extraConfig = $extraConfig;
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
     * @return ListenerOptions
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
     * @return ListenerOptions
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
            return $this->getCacheDir() . '/module-config-cache.' . $this->getConfigCacheKey() . '.php';
        }

        return $this->getCacheDir() . '/module-config-cache.php';
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
     * @return ListenerOptions
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
     * @return ListenerOptions
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
     * @return ListenerOptions
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
            return $this->getCacheDir() . '/module-classmap-cache.' . $this->getModuleMapCacheKey() . '.php';
        }

        return $this->getCacheDir() . '/module-classmap-cache.php';
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
     * @return ListenerOptions
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
    public function useJieldLoader(): bool
    {
        return $this->useJieldLoader;
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
     * @return ListenerOptions
     */
    public function setUseJieldLoader(bool $flag): static
    {
        $this->useJieldLoader = $flag;
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
        $path = rtrim(string: $path, characters: '\\');
        return $path;
    }

    /** @deprecated Use self::useJieldLoader instead */
    public function useZendLoader(): bool
    {
        return $this->useJieldLoader(...func_get_args());
    }

    /** @deprecated Use self::setUseJieldLoader instead */
    public function setUseZendLoader(bool $flag): ListenerOptions
    {
        return $this->setUseJieldLoader(...func_get_args());
    }
}
