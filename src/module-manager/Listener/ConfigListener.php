<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\Config\Config;
use Jield\Config\Factory as ConfigFactory;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\ModuleManager\ModuleEvent;
use Jield\Stdlib\ArrayUtils;
use Jield\Stdlib\Glob;
use Override;
use Traversable;
use function file_exists;
use function gettype;
use function is_array;
use function is_callable;
use function is_string;
use function sprintf;

class ConfigListener extends AbstractListener implements
    ConfigMergerInterface,
    ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    public const string STATIC_PATH = 'static_path';
    public const string GLOB_PATH = 'glob_path';

    /** @var array */
    protected array $configs = [];

    /** @var array */
    protected array $mergedConfig = [];

    /** @var Config|null */
    protected ?Config $mergedConfigObject;

    /** @var bool */
    protected bool $skipConfig = false;

    /** @var array */
    protected array $paths = [];

    public function __construct(?ListenerOptions $options = null)
    {
        parent::__construct(options: $options);
        if ($this->hasCachedConfig()) {
            $this->skipConfig = true;
            $this->setMergedConfig(config: $this->getCachedConfig());
        } else {
            $this->addConfigGlobPaths(globPaths: $this->getOptions()->getConfigGlobPaths());
            $this->addConfigStaticPaths(staticPaths: $this->getOptions()->getConfigStaticPaths());
        }
    }

    /** {@inheritDoc} */
    #[Override]
    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULES, listener: [$this, 'onloadModulesPre'], priority: 1000);

        if ($this->skipConfig) {
            // We already have the config from cache, no need to collect or merge.
            return;
        }

        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULE, listener: [$this, 'onLoadModule']);
        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULES, listener: [$this, 'onLoadModules'], priority: -1000);
        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_MERGE_CONFIG, listener: [$this, 'onMergeConfig'], priority: 1000);
    }

    /**
     * Pass self to the ModuleEvent object early so everyone has access.
     *
     * @return ConfigListener
     */
    public function onloadModulesPre(ModuleEvent $e): static
    {
        $e->setConfigListener(configListener: $this);

        return $this;
    }

    /**
     * Merge the config for each module
     *
     * @return ConfigListener
     */
    public function onLoadModule(ModuleEvent $e): static
    {
        $module = $e->getModule();

        if (
            !$module instanceof ConfigProviderInterface
            && !is_callable(value: [$module, 'getConfig'])
        ) {
            return $this;
        }

        $config = $module->getConfig();
        $this->addConfig(key: $e->getModuleName(), config: $config);

        return $this;
    }

    /**
     * Merge all config files matched by the given glob()s
     *
     * This is only attached if config is not cached.
     *
     * @return ConfigListener
     */
    public function onMergeConfig(ModuleEvent $e): static
    {
        // Load the config files
        foreach ($this->paths as $path) {
            $this->addConfigByPath(path: $path['path'], type: $path['type']);
        }

        // Merge all of the collected configs
        $this->mergedConfig = $this->getOptions()->getExtraConfig() ?: [];
        foreach ($this->configs as $config) {
            $this->mergedConfig = ArrayUtils::merge(a: $this->mergedConfig, b: $config);
        }

        return $this;
    }

    /**
     * Optionally cache merged config
     *
     * This is only attached if config is not cached.
     *
     * @return ConfigListener
     */
    public function onLoadModules(ModuleEvent $e): static
    {
        // Trigger MERGE_CONFIG event. This is a hook to allow the merged application config to be
        // modified before it is cached (In particular, allows the removal of config keys)
        $originalEventName = $e->getName();
        $e->setName(name: ModuleEvent::EVENT_MERGE_CONFIG);
        $e->getTarget()->getEventManager()->triggerEvent($e);

        // Reset event name
        $e->setName(name: $originalEventName);

        // If enabled, update the config cache
        if (
            $this->getOptions()->getConfigCacheEnabled()
            && false === $this->skipConfig
        ) {
            $configFile = $this->getOptions()->getConfigCacheFile();
            $this->writeArrayToFile(filePath: $configFile, array: $this->getMergedConfig(returnConfigAsObject: false));
        }

        return $this;
    }

    /**
     * @param bool $returnConfigAsObject
     * @return mixed
     */
    #[Override]
    public function getMergedConfig(bool $returnConfigAsObject = true): mixed
    {
        if ($returnConfigAsObject === true) {
            if ($this->mergedConfigObject === null) {
                $this->mergedConfigObject = new Config($this->mergedConfig);
            }
            return $this->mergedConfigObject;
        }

        return $this->mergedConfig;
    }

    /**
     * @return ConfigListener
     */
    #[Override]
    public function setMergedConfig(array $config): static
    {
        $this->mergedConfig       = $config;
        $this->mergedConfigObject = null;
        return $this;
    }

    /**
     * Add an array of glob paths of config files to merge after loading modules
     *
     * @param Traversable|array $globPaths
     * @return ConfigListener
     */
    public function addConfigGlobPaths(Traversable|array $globPaths): static
    {
        $this->addConfigPaths(paths: $globPaths, type: self::GLOB_PATH);
        return $this;
    }

    /**
     * Add a glob path of config files to merge after loading modules
     *
     * @param string $globPath
     * @return ConfigListener
     */
    public function addConfigGlobPath(string $globPath): static
    {
        $this->addConfigPath(path: $globPath, type: self::GLOB_PATH);
        return $this;
    }

    /**
     * Add an array of static paths of config files to merge after loading modules
     *
     * @param Traversable|array $staticPaths
     * @return ConfigListener
     */
    public function addConfigStaticPaths(Traversable|array $staticPaths): static
    {
        $this->addConfigPaths(paths: $staticPaths, type: self::STATIC_PATH);
        return $this;
    }

    /**
     * Add a static path of config files to merge after loading modules
     *
     * @param string $staticPath
     * @return ConfigListener
     */
    public function addConfigStaticPath(string $staticPath): static
    {
        $this->addConfigPath(path: $staticPath, type: self::STATIC_PATH);
        return $this;
    }

    /**
     * Add an array of paths of config files to merge after loading modules
     *
     * @param Traversable|array $paths
     * @param string $type
     * @throws Exception\InvalidArgumentException
     */
    protected function addConfigPaths(Traversable|array $paths, string $type): void
    {
        if ($paths instanceof Traversable) {
            $paths = ArrayUtils::iteratorToArray(iterator: $paths);
        }

        if (!is_array(value: $paths)) {
            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    'Argument passed to %s::%s() must be an array, '
                    . 'implement the Traversable interface, or be an '
                    . 'instance of Jield\Config\Config. %s given.',
                    self::class,
                    __METHOD__,
                    gettype(value: $paths)
                )
            );
        }

        foreach ($paths as $path) {
            $this->addConfigPath(path: $path, type: $type);
        }
    }

    /**
     * Add a path of config files to load and merge after loading modules
     *
     * @param string $path
     * @param string $type
     * @return ConfigListener
     * @throws Exception\InvalidArgumentException
     */
    protected function addConfigPath(string $path, string $type): static
    {
        if (!is_string(value: $path)) {
            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    'Parameter to %s::%s() must be a string; %s given.',
                    self::class,
                    __METHOD__,
                    gettype(value: $path)
                )
            );
        }
        $this->paths[] = ['type' => $type, 'path' => $path];
        return $this;
    }

    /**
     * @param string $key
     * @param Traversable|array $config
     * @return ConfigListener
     * @throws Exception\InvalidArgumentException
     */
    protected function addConfig(string $key, Traversable|array $config): static
    {
        if ($config instanceof Traversable) {
            $config = ArrayUtils::iteratorToArray(iterator: $config);
        }

        if (!is_array(value: $config)) {
            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    'Config being merged must be an array, '
                    . 'implement the Traversable interface, or be an '
                    . 'instance of Jield\Config\Config. %s given.',
                    gettype(value: $config)
                )
            );
        }

        $this->configs[$key] = $config;

        return $this;
    }

    /**
     * Given a path (glob or static), fetch the config and add it to the array
     * of configs to merge.
     *
     * @param string $path
     * @param string $type
     * @return ConfigListener
     */
    protected function addConfigByPath(string $path, string $type): static
    {
        switch ($type) {
            case self::STATIC_PATH:
                $this->addConfig(key: $path, config: ConfigFactory::fromFile($path));
                break;

            case self::GLOB_PATH:
                // We want to keep track of where each value came from so we don't
                // use ConfigFactory::fromFiles() since it does merging internally.
                foreach (Glob::glob(pattern: $path, flags: Glob::GLOB_BRACE, forceFallback: true) as $file) {
                    $this->addConfig(key: $file, config: ConfigFactory::fromFile($file));
                }
                break;
        }

        return $this;
    }

    /** @return bool */
    protected function hasCachedConfig(): bool
    {
        if (
            ($this->getOptions()->getConfigCacheEnabled())
            && (file_exists(filename: $this->getOptions()->getConfigCacheFile()))
        ) {
            return true;
        }
        return false;
    }

    /** @return mixed */
    protected function getCachedConfig(): mixed
    {
        return include $this->getOptions()->getConfigCacheFile();
    }
}
