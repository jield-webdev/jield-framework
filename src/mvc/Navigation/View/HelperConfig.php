<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\View;

use Jield\Mvc\Navigation\View\Navigation as NavigationHelper;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\Stdlib\ArrayUtils;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;
use Traversable;

use function in_array;
use function is_array;
use function iterator_to_array;

/**
 * Service manager configuration for navigation view helpers.
 *
 * Replaces the Service Manager 2/3 `Laminas\ServiceManager\Config` approach
 * (removed in Service Manager 4) with a plain configuration builder that calls
 * {@see HelperPluginManager::configure()} directly.
 *
 * @final
  * @psalm-import-type ServiceManagerConfiguration from \Laminas\ServiceManager\ServiceManager
 */
class HelperConfig
{
    /**
     * Default configuration to apply.
     *
     * @var array<string, mixed>
     */
    protected array $config = [
        'aliases'    => [
            'navigation' => NavigationHelper::class,
            'Navigation' => NavigationHelper::class,
        ],
        'delegators' => [],
        'factories'  => [
            NavigationHelper::class       => NavigationHelperFactory::class,
            'laminasviewhelpernavigation' => NavigationHelperFactory::class,
        ],
    ];

    /**
     * Navigation helper delegator factory.
     *
     * @var (callable(ContainerInterface, string, callable(): object, array<mixed>|null): object)|null
     */
    private $navigationDelegatorFactory;

    /**
     * Ensure incoming configuration is merged with the defaults defined.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->mergeConfig($config);
    }

    /**
     * Configure the provided view helper plugin manager.
     *
     * Merges `navigation_helpers` configuration from the application `config`
     * service, registers a delegator that seeds the proxy helper's own plugin
     * manager, and applies the resulting configuration to the view helper
     * plugin manager.
     */
    public function configureServiceManager(HelperPluginManager $viewHelpers, ContainerInterface $appContainer): void
    {
        if ($appContainer->has('config')) {
            $this->mergeHelpersFromConfiguration($appContainer->get('config'));
        }

        $this->injectNavigationDelegatorFactory();

        /** @var ServiceManagerConfiguration $managerConfiguration */
        $managerConfiguration = $this->config;
        $viewHelpers->configure($managerConfiguration);
    }

    /**
     * Merge an array of configuration with the settings already present.
     *
     * Processes invokables as invokable factories and optional aliases.
     *
     * @param array<string, mixed> $config
     */
    private function mergeConfig(array $config): void
    {
        if (isset($config['invokables'])) {
            $config = $this->processInvokables(\Jield\Stdlib\Value::stringMap($config['invokables']), $config);
        }

        /** @var mixed $services */
        foreach ($config as $type => $services) {
            if (! is_array($services)) {
                continue;
            }

            $this->config[$type] = isset($this->config[$type]) && is_array($this->config[$type])
                ? ArrayUtils::merge($this->config[$type], $services)
                : $services;
        }
    }

    /**
     * Merge navigation helper configuration with the default configuration.
     *
     * @param Traversable<string, mixed>|array<string, mixed> $config
     */
    private function mergeHelpersFromConfiguration(array|Traversable $config): void
    {
        if ($config instanceof Traversable) {
            $config = iterator_to_array($config);
        }

        if (
            ! isset($config['navigation_helpers'])
            || (! is_array($config['navigation_helpers']) && ! $config['navigation_helpers'] instanceof Traversable)
        ) {
            return;
        }

        $navigationHelpers = $config['navigation_helpers'];
        if ($navigationHelpers instanceof Traversable) {
            $navigationHelpers = iterator_to_array($navigationHelpers);
        }

        $this->mergeConfig(\Jield\Stdlib\Value::stringKeyedArray($navigationHelpers));
    }

    /**
     * Process invokables in order to seed aliases and factories.
     *
     * @param array<string, string> $invokables Array of invokables defined.
     * @param array<string, mixed> $config All service configuration.
     * @return array<string, mixed> Array of all service configuration.
     */
    private function processInvokables(array $invokables, array $config): array
    {
        $config['aliases']   ??= [];
        $config['factories'] ??= [];

        foreach ($invokables as $name => $class) {
            \Jield\Stdlib\Value::setPath($config, ['factories', $class], InvokableFactory::class);

            if ($name !== $class) {
                \Jield\Stdlib\Value::setPath($config, ['aliases', $name], $class);
            }
        }

        unset($config['invokables']);

        return \Jield\Stdlib\Value::array($config);
    }

    /**
     * Inject the navigation helper delegator factory into the configuration.
     */
    private function injectNavigationDelegatorFactory(): void
    {
        $factory = $this->prepareNavigationDelegatorFactory();

        if (
            isset($this->config['delegators'][NavigationHelper::class])
            && in_array($factory, \Jield\Stdlib\Value::array($this->config['delegators'][NavigationHelper::class]), true)
        ) {
            // Already present.
            return;
        }

        \Jield\Stdlib\Value::setPath($this->config, ['delegators', NavigationHelper::class, null], $factory);
        \Jield\Stdlib\Value::setPath($this->config, ['delegators', 'laminasviewhelpernavigation', null], $factory);
    }

    /**
     * Return a delegator factory that seeds the navigation proxy helper's own
     * plugin manager with the configuration held by this class.
     *
     * @return callable(ContainerInterface, string, callable(): object, array<mixed>|null): object
     */
    private function prepareNavigationDelegatorFactory(): callable
    {
        if ($this->navigationDelegatorFactory !== null) {
            return \Jield\Stdlib\Value::callback($this->navigationDelegatorFactory);
        }

        $config = $this->config;

        $this->navigationDelegatorFactory = static function (
            ContainerInterface $container,
            string $name,
            callable $callback,
            ?array $options = null
        ) use ($config): object {
            /** @var mixed $helper */
            $helper = $callback();

            // Seed the proxy helper's sub-helper plugin manager with the same
            // configuration, so custom navigation helpers propagate to it.
            \Jield\Stdlib\Value::call(\Jield\Stdlib\Value::call($helper, 'getPluginManager'), 'configure', [$config]);

            return \Jield\Stdlib\Value::object($helper);
        };

        return \Jield\Stdlib\Value::callback($this->navigationDelegatorFactory);
    }
}
