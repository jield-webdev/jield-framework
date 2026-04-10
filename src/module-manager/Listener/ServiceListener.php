<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Jield\ModuleManager\ModuleEvent;
use Laminas\EventManager\EventManagerInterface;
use Laminas\ServiceManager\Config as ServiceConfig;
use Laminas\ServiceManager\ConfigInterface as ServiceConfigInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Override;
use Traversable;
use function class_exists;
use function gettype;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;
use function spl_object_hash;
use function sprintf;

class ServiceListener implements ServiceListenerInterface
{
    protected array $listeners = [];

    /**
     * Default service configuration for the application service manager.
     *
     * @var array
     */
    protected array $defaultServiceConfig;

    /** @var array */
    protected array $serviceManagers = [];

    public function __construct(
        /**
         * Default service manager used to fulfill other SMs that need to be lazy loaded
         */
        protected ServiceManager $defaultServiceManager,
        ?array                   $configuration = null
    )
    {
        if ($configuration !== null) {
            $this->setDefaultServiceConfig(configuration: $configuration);
        }
    }

    /**
     * @param array $configuration
     * @return ServiceListener
     */
    #[Override]
    public function setDefaultServiceConfig(array $configuration): static
    {
        $this->defaultServiceConfig = $configuration;
        return $this;
    }

    /** {@inheritDoc} */
    #[Override]
    public function addServiceManager(string $serviceManager, string $key, string $moduleInterface, string $method): ServiceListenerInterface|static
    {
        if (is_string(value: $serviceManager)) {
            $smKey = $serviceManager;
        } elseif ($serviceManager instanceof ServiceManager) {
            $smKey = spl_object_hash(object: $serviceManager);
        } else {
            throw new Exception\RuntimeException(message: sprintf(
                'Invalid service manager provided, expected ServiceManager or string, %s provided',
                is_object(value: $serviceManager) ? $serviceManager::class : gettype(value: $serviceManager)
            ));
        }

        $this->serviceManagers[$smKey] = [
            'service_manager'        => $serviceManager,
            'config_key'             => $key,
            'module_class_interface' => $moduleInterface,
            'module_class_method'    => $method,
            'configuration'          => [],
        ];

        if ($key === 'service_manager' && $this->defaultServiceConfig) {
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
        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULE, listener: [$this, 'onLoadModule']);
        $this->listeners[] = $events->attach(eventName: ModuleEvent::EVENT_LOAD_MODULES_POST, listener: [$this, 'onLoadModulesPost']);
    }

    /** @return void */
    #[Override]
    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $key => $listener) {
            if ($events->detach(listener: $listener)) {
                unset($this->listeners[$key]);
            }
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
        $module = $e->getModule();

        foreach ($this->serviceManagers as $key => $sm) {
            if (
                !$module instanceof $sm['module_class_interface']
                && !method_exists(object_or_class: $module, method: $sm['module_class_method'])
            ) {
                continue;
            }

            $config = $module->{$sm['module_class_method']}();

            if ($config instanceof ServiceConfigInterface) {
                $config = $this->serviceConfigToArray(config: $config);
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
            $fullname = $e->getModuleName() . '::' . $sm['module_class_method'] . '()';
            /** @codingStandardsIgnoreLine */
            $this->serviceManagers[$key]['configuration'][$fullname] = $config;
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
        $config         = $configListener->getMergedConfig(returnConfigAsObject: false);

        foreach ($this->serviceManagers as $key => $sm) {
            $smConfig = $this->mergeServiceConfiguration(key: $key, metadata: $sm, config: $config);

            if (!$sm['service_manager'] instanceof ServiceManager) {
                if (!$this->defaultServiceManager->has(id: $sm['service_manager'])) {
                    // No plugin manager registered by that name; nothing to configure.
                    continue;
                }

                $instance = $this->defaultServiceManager->get(id: $sm['service_manager']);
                if (!$instance instanceof ServiceManager) {
                    throw new Exception\RuntimeException(message: sprintf(
                        'Could not find a valid ServiceManager for %s',
                        $sm['service_manager']
                    ));
                }

                $sm['service_manager'] = $instance;
            }

            $serviceConfig = new ServiceConfig($smConfig);

            // The service listener is meant to operate during bootstrap, and, as such,
            // needs to be able to override existing configuration.
            $allowOverride = $sm['service_manager']->getAllowOverride();
            $sm['service_manager']->setAllowOverride(flag: true);

            $serviceConfig->configureServiceManager(services: $sm['service_manager']);

            $sm['service_manager']->setAllowOverride(flag: $allowOverride);
        }
    }

    /**
     * Merge a service configuration container
     *
     * Extracts the various service configuration arrays.
     *
     * @param string|ServiceConfigInterface $config ServiceConfigInterface or
     *     class name resolving to one.
     * @return array
     * @throws Exception\RuntimeException If resolved class name is not a
     *     ServiceConfigInterface implementation.
     * @throws Exception\RuntimeException Under jield-servicemanager v2 if the
     *     configuration instance is not specifically a ServiceConfig, as there
     *     is no way to extract service configuration in that case.
     */
    protected function serviceConfigToArray(ServiceConfigInterface|string $config): array
    {
        if (is_string(value: $config) && class_exists(class: $config)) {
            $class  = $config;
            $config = new $class();
        }

        if (!$config instanceof ServiceConfigInterface) {
            throw new Exception\RuntimeException(message: sprintf(
                'Invalid service manager configuration class provided; received "%s", expected an instance of %s',
                is_object(value: $config) ? $config::class : (is_scalar(value: $config) ? $config : gettype(value: $config)),
                ServiceConfigInterface::class
            ));
        }

        if (method_exists(object_or_class: $config, method: 'toArray')) {
            // jield-servicemanager v3 interface
            return $config->toArray();
        }

        // For jield-servicemanager v2, we need a Laminas\ServiceManager\Config
        // instance specifically.
        if (!$config instanceof ServiceConfig) {
            throw new Exception\RuntimeException(message: sprintf(
                'Invalid service manager configuration class provided; received "%s", expected an instance of %s',
                is_object(value: $config) ? $config::class : (is_scalar(value: $config) ? $config : gettype(value: $config)),
                ServiceConfig::class
            ));
        }

        // Pull service configuration from discrete methods.
        return [
            'abstract_factories' => $config->getAbstractFactories(),
            'aliases'            => $config->getAliases(),
            'delegators'         => $config->getDelegators(),
            'factories'          => $config->getFactories(),
            'initializers'       => $config->getInitializers(),
            'invokables'         => $config->getInvokables(),
            'services'           => $config->getServices(),
            'shared'             => $config->getShared(),
        ];
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
            $this->serviceManagers[$key]['configuration']['merged_config'] = $config[$metadata['config_key']];
        }

        // Merge all of the things!
        $serviceConfig = [];
        foreach ($this->serviceManagers[$key]['configuration'] as $configs) {
            if (isset($configs['configuration_classes'])) {
                foreach ($configs['configuration_classes'] as $class) {
                    $configs = ArrayUtils::merge(a: $configs, b: $this->serviceConfigToArray(config: $class));
                }
            }
            $serviceConfig = ArrayUtils::merge(a: $serviceConfig, b: $configs);
        }

        return $serviceConfig;
    }
}
