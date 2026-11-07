<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\ModuleManager\ModuleEvent;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\Glob;
use Override;
use Traversable;

use function class_exists;
use function file_exists;
use function gettype;
use function is_array;
use function is_callable;
use function is_file;
use function sprintf;

class ConfigListener extends AbstractListener implements ConfigMergerInterface, ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    public const string STATIC_PATH = 'static_path';
    public const string GLOB_PATH = 'glob_path';

    protected array $configs = [];
    protected array $mergedConfig = [];
    protected bool $skipConfig = false;
    protected array $paths = [];

    public function __construct(?ListenerOptions $options = null)
    {
        parent::__construct(options: $options);
        if ($this->hasCachedConfig()) {
            $this->skipConfig = true;
            $this->setMergedConfig(config: Value::array($this->getCachedConfig()));
        } else {
            $this->addConfigGlobPaths(globPaths: $this->getOptions()->getConfigGlobPaths());
            $this->addConfigStaticPaths(staticPaths: $this->getOptions()->getConfigStaticPaths());
        }
    }

    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_LOAD_MODULES,
            listener: $this->onloadModulesPre(...),
            priority: 1000,
        );

        if ($this->skipConfig) {
            // We already have the config from cache, no need to collect or merge.
            return;
        }

        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_LOAD_MODULE,
            listener: $this->onLoadModule(...),
        );
        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_LOAD_MODULES,
            listener: $this->onLoadModules(...),
            priority: -1000,
        );
        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_MERGE_CONFIG,
            listener: $this->onMergeConfig(...),
            priority: 1000,
        );
    }

    /**
     * Pass self to the ModuleEvent object early so everyone has access.
     *
     * @return static
     */
    public function onloadModulesPre(ModuleEvent $e): static
    {
        $e->setConfigListener(configListener: $this);

        return $this;
    }

    /**
     * Merge the config for each module
     *
     * @return static
     */
    public function onLoadModule(ModuleEvent $e): static
    {
        $module = $e->getModule();
        $config = null;

        if ($module instanceof ConfigProviderInterface || is_callable(value: [$module, 'getConfig'])) {
            $config = Value::call(Value::present($module), 'getConfig');
        } elseif (is_callable(value: $module)) {
            $config = $this->getConfigFromProvider($module);
        } else {
            $configProvider = $this->resolveConfigProvider(moduleName: $e->getModuleName());
            if ($configProvider !== null) {
                $config = $this->getConfigFromProvider($configProvider);
            }
        }

        if ($config === null) {
            return $this;
        }

        $config = $this->normalizeServiceManagerConfig(Value::array($config));
        $this->addConfig(key: $e->getModuleName(), config: $config);

        return $this;
    }

    private function resolveConfigProvider(string $moduleName): ?object
    {
        $configProviderClass = sprintf('%s\\ConfigProvider', $moduleName);
        if (!class_exists(class: $configProviderClass)) {
            return null;
        }

        return Value::instantiate($configProviderClass);
    }

    private function getConfigFromProvider(object $provider): mixed
    {
        if (is_callable(value: $provider)) {
            return $provider();
        }

        if (is_callable(value: [$provider, 'getConfig'])) {
            return Value::call($provider, 'getConfig');
        }

        if (is_callable(value: [$provider, 'toArray'])) {
            return Value::call($provider, 'toArray');
        }

        return null;
    }

    /**
     * Merge all config files matched by the given glob()s
     *
     * This is only attached if config is not cached.
     *
     * @return static
     */
    public function onMergeConfig(): static
    {
        // Load the config files
        foreach ($this->paths as $path) {
            $this->addConfigByPath(
                path: Value::string(Value::accessible($path)['path']),
                type: Value::string(Value::accessible($path)['type']),
            );
        }

        // Merge all of the collected configs
        $extraConfig = $this->getOptions()->getExtraConfig();
        $this->mergedConfig = is_array($extraConfig) ? $extraConfig : iterator_to_array($extraConfig);
        foreach ($this->configs as $config) {
            $this->mergedConfig = ArrayUtils::merge(a: $this->mergedConfig, b: Value::array($config));
        }
        $this->mergedConfig = $this->normalizeServiceManagerConfig($this->mergedConfig);

        return $this;
    }

    /**
     * Optionally cache merged config
     *
     * This is only attached if config is not cached.
     *
     * @return static
     */
    public function onLoadModules(ModuleEvent $e): static
    {
        // Trigger MERGE_CONFIG event. This is a hook to allow the merged application config to be
        // modified before it is cached (In particular, allows the removal of config keys)
        $originalEventName = $e->getName();
        $e->setName(name: ModuleEvent::EVENT_MERGE_CONFIG);
        Value::call(
            Value::call(
                Value::present(Value::object($e->getTarget())),
                'getEventManager',
            ),
            'triggerEvent',
            [$e],
        );

        // Reset event name
        $e->setName(name: Value::string($originalEventName));

        // If enabled, update the config cache
        if ($this->getOptions()->getConfigCacheEnabled() && false === $this->skipConfig) {
            $configFile = $this->getOptions()->getConfigCacheFile();
            $this->writeArrayToFile(
                filePath: $configFile,
                array: Value::array($this->getMergedConfig(returnConfigAsObject: false)),
            );
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
        return $this->mergedConfig;
    }

    /**
     * @return static
     */
    #[Override]
    public function setMergedConfig(array $config): static
    {
        $this->mergedConfig = $this->normalizeServiceManagerConfig($config);
        return $this;
    }

    /**
     * Add an array of glob paths of config files to merge after loading modules
     *
     * @param Traversable|array $globPaths
     * @return static
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
     * @return static
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
     * @return static
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
     * @return static
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
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Argument passed to %s::%s() must be an array, '
                . 'implement the Traversable interface, or be an '
                . 'instance of Jield\Config\Config. %s given.',
                self::class,
                __METHOD__,
                gettype(value: $paths),
            ));
        }

        foreach ($paths as $path) {
            $this->addConfigPath(path: Value::string($path), type: $type);
        }
    }

    /**
     * Add a path of config files to load and merge after loading modules
     *
     * @param string $path
     * @param string $type
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    protected function addConfigPath(string $path, string $type): static
    {
        $this->paths[] = ['type' => $type, 'path' => $path];
        return $this;
    }

    /**
     * @param string $key
     * @param Traversable|array $config
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    protected function addConfig(string $key, Traversable|array $config): static
    {
        if ($config instanceof Traversable) {
            $config = ArrayUtils::iteratorToArray(iterator: $config);
        }

        if (!is_array(value: $config)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Config being merged must be an array, '
                . 'implement the Traversable interface, or be an '
                . 'instance of Jield\Config\Config. %s given.',
                gettype(value: $config),
            ));
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
     * @return static
     */
    protected function addConfigByPath(string $path, string $type): static
    {
        switch ($type) {
            case self::STATIC_PATH:
                $this->addConfig(key: $path, config: $this->loadConfigFile($path));
                break;

            case self::GLOB_PATH:
                // We want to keep track of where each value came from so we don't
                // use ConfigFactory::fromFiles() since it does merging internally.
                /** @var mixed $file */
                foreach (Glob::glob(pattern: $path, flags: Glob::GLOB_BRACE, forceFallback: true) as $file) {
                    $this->addConfig(
                        key: Value::string($file),
                        config: $this->loadConfigFile(Value::string($file)),
                    );
                }
                break;
        }

        return $this;
    }

    /** @return bool */
    protected function hasCachedConfig(): bool
    {
        if (
            $this->getOptions()->getConfigCacheEnabled()
            && file_exists(filename: $this->getOptions()->getConfigCacheFile())
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

    /**
     * Load a config file that returns an array.
     *
     * @throws Exception\InvalidArgumentException
     */
    private function loadConfigFile(string $path): array
    {
        if (!is_file($path)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Config file "%s" does not exist or is not a file.',
                $path,
            ));
        }

        $config = include $path;
        if (!is_array(value: $config)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Config file "%s" must return an array; %s given.',
                $path,
                gettype(value: $config),
            ));
        }

        return $config;
    }

    /**
     * Normalize service manager configuration keys for v4 compatibility.
     *
     * Laminas ServiceManager v4 uses "dependencies" as the preferred config
     * key, while older code still expects "service_manager". Keep both in sync.
     */
    private function normalizeServiceManagerConfig(array $config): array
    {
        $hasDependencies = isset($config['dependencies']) && is_array(value: $config['dependencies']);
        $hasServiceConfig = isset($config['service_manager']) && is_array(value: $config['service_manager']);

        if (!$hasDependencies && !$hasServiceConfig) {
            return $config;
        }

        $normalized = [];
        if ($hasDependencies) {
            $normalized = ArrayUtils::merge(a: $normalized, b: $config['dependencies']);
        }
        if ($hasServiceConfig) {
            $normalized = ArrayUtils::merge(a: $normalized, b: $config['service_manager']);
        }

        // Factory callables are atomic pairs; recursive list merging would duplicate them.
        foreach (['factories', 'aliases', 'services'] as $key) {
            $normalized[$key] = array_replace(
                Value::array($config['dependencies'][$key] ?? []),
                Value::array($config['service_manager'][$key] ?? []),
            );
        }

        $config['service_manager'] = $normalized;
        $config['dependencies'] = $normalized;

        return $config;
    }
}
