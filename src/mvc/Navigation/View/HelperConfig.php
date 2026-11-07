<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\View;

use Jield\Mvc\Navigation\View\Navigation as NavigationHelper;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;
use Traversable;
use Webmozart\Assert\Assert;

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
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
class HelperConfig
{
    /**
     * Default configuration to apply.
     *
     * @var array<string, mixed>
     */
    protected array $config = [
        'aliases' => [
            'navigation' => NavigationHelper::class,
            'Navigation' => NavigationHelper::class,
        ],
        'delegators' => [],
        'factories' => [
            NavigationHelper::class => NavigationHelperFactory::class,
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
            /** @mago-var mixed $config */
            $config = $appContainer->get('config');
            $this->mergeHelpersFromConfiguration(Value::stringKeyedArray($config instanceof Traversable ? iterator_to_array($config) : $config));
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
            $config = $this->processInvokables(Value::stringMap($config['invokables']), $config);
        }

        /** @mago-var mixed $services */
        foreach ($config as $type => $services) {
            if (!is_array($services)) {
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
            !isset($config['navigation_helpers'])
            || !is_array($config['navigation_helpers']) && !$config['navigation_helpers'] instanceof Traversable
        ) {
            return;
        }

        $navigationHelpers = $config['navigation_helpers'];
        if ($navigationHelpers instanceof Traversable) {
            $navigationHelpers = iterator_to_array($navigationHelpers);
        }

        $this->mergeConfig(Value::stringKeyedArray($navigationHelpers));
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
        $config['aliases'] ??= [];
        $config['factories'] ??= [];

        foreach ($invokables as $name => $class) {
            Value::setPath($config, ['factories', $class], InvokableFactory::class);

            if ($name !== $class) {
                Value::setPath($config, ['aliases', $name], $class);
            }
        }

        unset($config['invokables']);

        return Value::stringKeyedArray($config);
    }

    /**
     * Inject the navigation helper delegator factory into the configuration.
     */
    private function injectNavigationDelegatorFactory(): void
    {
        $factory = $this->prepareNavigationDelegatorFactory();

        if (
            isset($this->config['delegators'][NavigationHelper::class])
            && in_array($factory, Assert::isArray($this->config['delegators'][NavigationHelper::class]), true)
        ) {
            // Already present.
            return;
        }

        $delegators = Assert::isArray($this->config['delegators'] ?? []);
        Value::setPath($delegators, [NavigationHelper::class, null], $factory);
        Value::setPath($delegators, ['laminasviewhelpernavigation', null], $factory);
        $this->config['delegators'] = $delegators;
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
            return $this->navigationDelegatorFactory;
        }

        $config = $this->config;

        $this->navigationDelegatorFactory = static function (
            ContainerInterface $container,
            string $name,
            callable $callback,
            ?array $options = null,
        ) use ($config): object {
            /** @mago-var mixed $helper */
            $helper = $callback();

            // Seed the proxy helper's sub-helper plugin manager with the same
            // configuration, so custom navigation helpers propagate to it.
            Value::call(Value::call($helper, 'getPluginManager'), 'configure', [$config]);

            return Assert::object($helper);
        };

        return $this->navigationDelegatorFactory;
    }
}
