<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\ModuleEvent;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventManagerInterface;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Override;
use Traversable;
use Webmozart\Assert\Assert;

use function class_exists;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function spl_object_hash;
use function sprintf;

/** @psalm-import-type ServiceManagerConfiguration from \Laminas\ServiceManager\ServiceManager */
class ServiceListener implements ServiceListenerInterface
{
    protected array $listeners = [];

    /**
     * Default service configuration for the application service manager.
     *
     * @var array
     */
    protected array $defaultServiceConfig = [];

    /** @var array */
    protected array $serviceManagers = [];

    public function __construct(
        /**
         * Default service manager used to fulfill other SMs that need to be lazy loaded
         */
        protected ServiceManager $defaultServiceManager,
        ?array $configuration = null,
    ) {
        if ($configuration !== null) {
            $this->setDefaultServiceConfig(configuration: $configuration);
        }
    }

    /**
     * @param array $configuration
     * @return static
     */
    #[Override]
    public function setDefaultServiceConfig(array $configuration): static
    {
        $this->defaultServiceConfig = $configuration;
        return $this;
    }

    /** {@inheritDoc} */
    #[Override]
    public function addServiceManager(
        ServiceManager|AbstractPluginManager|string $serviceManager,
        string $key,
        string $moduleInterface,
        string $method,
    ): static {
        $smKey = is_string($serviceManager) ? $serviceManager : spl_object_hash($serviceManager);

        $this->serviceManagers[$smKey] = [
            'service_manager' => $serviceManager,
            'config_key' => $key,
            'module_class_interface' => $moduleInterface,
            'module_class_method' => $method,
            'configuration' => [],
        ];

        if ($key === 'service_manager' && $this->defaultServiceConfig !== []) {
            $this->serviceManagers[$smKey]['configuration']['default_config'] = $this->defaultServiceConfig;
        }

        return $this;
    }

    /**
     * @param int $priority
     * @return void
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_LOAD_MODULE,
            listener: $this->onLoadModule(...),
        );
        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_LOAD_MODULES_POST,
            listener: $this->onLoadModulesPost(...),
        );
    }

    /** @return void */
    #[Override]
    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $key => $listener) {
            $events->detach(listener: $listener);

            unset($this->listeners[$key]);
        }
    }

    /**
     * Retrieve service manager configuration from module, and
     * configure the service manager.
     *
     * If the module does not implement a specific interface and does not
     * implement a specific method, does nothing. Also, if the return value
     * of that method is not a ServiceConfig object, or not an array or
     * Traversable that can seed one, does nothing.
     *
     * The interface and method name can be set by adding a new service manager
     * via the addServiceManager() method.
     *
     * @return void
     */
    public function onLoadModule(ModuleEvent $e): void
    {
        $module = Assert::object($e->getModule());

        foreach ($this->serviceManagers as $key => $sm) {
            $moduleClassInterface = Assert::string($sm['module_class_interface']);
            if (
                !$module instanceof $moduleClassInterface
                && !method_exists(object_or_class: $module, method: $sm['module_class_method'])
            ) {
                continue;
            }

            $config = Value::call($module, $sm['module_class_method']);

            if (is_object(value: $config)) {
                if (method_exists(object_or_class: $config, method: '__invoke')) {
                    $config = $config();
                } elseif (method_exists(object_or_class: $config, method: 'getConfig')) {
                    $config = $config->getConfig();
                } elseif (method_exists(object_or_class: $config, method: 'toArray')) {
                    $config = $config->toArray();
                }
            }

            if ($config instanceof Traversable) {
                $config = ArrayUtils::iteratorToArray(iterator: $config);
            }

            if (!is_array(value: $config)) {
                // If we do not have an array by this point, nothing left to do.
                continue;
            }

            // We are keeping track of which modules provided which configuration to which service managers.
            // The actual merging takes place later. Doing it this way will enable us to provide more powerful
            // debugging tools for showing which modules overrode what.
            $fullname = $e->getModuleName() . '::' . Value::stringify($sm['module_class_method']) . '()';
            /** @codingStandardsIgnoreLine */
            Value::setPath($this->serviceManagers, [$key, 'configuration', $fullname], $config);
        }
    }

    /**
     * Use merged configuration to configure service manager
     *
     * If the merged configuration has a non-empty, array 'service_manager'
     * key, it will be passed to a ServiceManager Config object, and
     * used to configure the service manager.
     *
     * @return void
     * @throws Exception\RuntimeException
     */
    public function onLoadModulesPost(ModuleEvent $e): void
    {
        $configListener = $e->getConfigListener();
        $config = Assert::notNull($configListener)->getMergedConfig(returnConfigAsObject: false);

        foreach ($this->serviceManagers as $key => $sm) {
            $smConfig = $this->mergeServiceConfiguration(key: Assert::string($key), metadata: $sm, config: $config);

            $manager = $sm['service_manager'];
            if (is_string($manager)) {
                if (!$this->defaultServiceManager->has($manager)) {
                    // No plugin manager registered by that name; nothing to configure.
                    continue;
                }

                $manager = $this->defaultServiceManager->get($manager);
            }

            if (!$manager instanceof ServiceManager && !$manager instanceof AbstractPluginManager) {
                throw new Exception\RuntimeException(message: sprintf(
                    'Could not find a valid ServiceManager for %s',
                    $sm['service_manager'],
                ));
            }

            // ServiceManager 4 plugin managers wrap a ServiceManager instead of extending it.
            // Both expose configuration and override controls needed during bootstrap.
            $allowOverride = $manager->getAllowOverride();
            $manager->setAllowOverride(flag: true);
            try {
                /** @var ServiceManagerConfiguration $managerConfiguration */
                $managerConfiguration = $smConfig;
                $manager->configure(config: $managerConfiguration);
            } finally {
                $manager->setAllowOverride(flag: $allowOverride);
            }
        }
    }

    /**
     * Merge a service configuration container
     *
     * Extracts the various service configuration arrays.
     *
     * @param string $config class name resolving to one.
     * @return array
     */
    protected function serviceConfigToArray(string $config): array
    {
        $className = $config;

        if (!class_exists(class: $className)) {
            throw new Exception\RuntimeException(message: sprintf(
                'Invalid service manager configuration class provided; "%s" could not be found',
                $className,
            ));
        }

        $instance = Value::instantiate($className);

        if (method_exists(object_or_class: $instance, method: '__invoke')) {
            $config = $instance();
        } elseif (method_exists(object_or_class: $instance, method: 'getConfig')) {
            $config = $instance->getConfig();
        } elseif (method_exists(object_or_class: $instance, method: 'toArray')) {
            $config = $instance->toArray();
        } elseif ($instance instanceof Traversable) {
            $config = ArrayUtils::iteratorToArray(iterator: $instance);
        } else {
            $config = null;
        }

        if (!is_array(value: $config)) {
            throw new Exception\RuntimeException(message: sprintf(
                'Invalid service manager configuration class provided; "%s" did not return an array',
                $className,
            ));
        }

        return $config;
    }

    /**
     * Merge all configuration for a given service manager to a single array.
     *
     * @param string $key Named service manager
     * @param array $metadata Service manager metadata
     * @param array $config Merged configuration
     * @return array Service manager-specific configuration
     */
    private function mergeServiceConfiguration(string $key, array $metadata, array $config): array
    {
        if (
            isset($config[$metadata['config_key']])
            && is_array(value: $config[$metadata['config_key']])
            && !empty($config[$metadata['config_key']])
        ) {
            Value::setPath(
                $this->serviceManagers,
                [$key, 'configuration', 'merged_config'],
                $config[$metadata['config_key']],
            );
        }

        // Merge all of the things!
        $serviceConfig = [];
        foreach (Assert::isIterable($this->serviceManagers[$key]['configuration']) as $configs) {
            if (isset($configs['configuration_classes'])) {
                foreach (Assert::isIterable($configs['configuration_classes']) as $class) {
                    $configs = ArrayUtils::merge(a: $configs, b: $this->serviceConfigToArray(config: $class));
                }
            }
            $serviceConfig = ArrayUtils::merge(a: $serviceConfig, b: $configs);
        }

        return $serviceConfig;
    }
}
