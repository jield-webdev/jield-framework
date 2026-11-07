<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\ModuleManager\Listener\ServiceListener;
use Jield\ModuleManager\Listener\ServiceListenerInterface;
use Jield\Mvc;
use Jield\Mvc\View\Http\JsonStrategy;
use Jield\Mvc\View\Renderer\PhpRenderer;
use Jield\Stdlib\Value;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Router\RoutePluginManager;
use Laminas\Router\RouteStackInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View;
use Psr\Container\ContainerInterface;

use function get_debug_type;
use function gettype;
use function is_array;
use function is_string;
use function sprintf;

class ServiceListenerFactory implements FactoryInterface
{
    /** @var string */
    public const string MISSING_KEY_ERROR = 'Invalid service listener options detected, %s array must contain %s key.';

    /** @var string */
    public const string VALUE_TYPE_ERROR = 'Invalid service listener options detected, %s must be a string, %s given.';

    /**
     * Default mvc-related service configuration -- can be overridden by modules.
     *
     * @var array
     */
    protected array $defaultServiceConfig = [
        'abstract_factories' => [ViewHelperAbstractFactory::class],
        'aliases' => [
            'ViewJsonStrategy' => JsonStrategy::class,
            'HttpRouter' => TreeRouteStack::class,
            'Router' => RouteStackInterface::class,
            'RoutePluginManager' => RoutePluginManager::class,
            'application' => 'Application',
            'Config' => 'config',
            'configuration' => 'config',
            'Configuration' => 'config',
            'HttpDefaultRenderingStrategy' => Mvc\View\Http\DefaultRenderingStrategy::class,
            'request' => 'Request',
            'response' => 'Response',
            'RouteListener' => Mvc\RouteListener::class,
            'SendResponseListener' => Mvc\SendResponseListener::class,
            'View' => View\View::class,
            View\HelperPluginManager::class => 'ViewHelperManager',
            View\HelperPluginManagerInterface::class => 'ViewHelperManager',
            'ViewPhpRenderer' => PhpRenderer::class,
            'ViewRenderer' => PhpRenderer::class,
            Mvc\Controller\PluginManager::class => 'ControllerPluginManager',
            Mvc\View\Http\InjectTemplateListener::class => 'InjectTemplateListener',
            View\Renderer\RendererInterface::class => PhpRenderer::class,
            View\Resolver\PrefixPathStackResolver::class => 'ViewPrefixPathStackResolver',
            View\Resolver\TemplateMapResolver::class => 'ViewTemplateMapResolver',
            View\Resolver\TemplatePathStack::class => 'ViewTemplatePathStack',
            View\Resolver\AggregateResolver::class => 'ViewResolver',
            View\Resolver\ResolverInterface::class => 'ViewResolver',
            Mvc\Controller\ControllerManager::class => 'ControllerManager',
        ],
        'invokables' => [],
        'factories' => [
            JsonStrategy::class => InvokableFactory::class,
            \Laminas\View\Renderer\PhpRenderer::class => NativePhpRendererFactory::class,
            'Application' => ApplicationFactory::class,
            'config' => Mvc\Factory\ConfigFactory::class,
            'ControllerManager' => Mvc\Factory\ControllerManagerFactory::class,
            'ControllerPluginManager' => Mvc\Factory\ControllerPluginManagerFactory::class,
            'DispatchListener' => Mvc\Factory\DispatchListenerFactory::class,
            'HttpExceptionStrategy' => HttpExceptionStrategyFactory::class,
            'HttpMethodListener' => Mvc\Factory\HttpMethodListenerFactory::class,
            'HttpRouteNotFoundStrategy' => HttpRouteNotFoundStrategyFactory::class,
            'HttpViewManager' => Mvc\Factory\HttpViewManagerFactory::class,
            'InjectTemplateListener' => Mvc\Factory\InjectTemplateListenerFactory::class,
            'PaginatorPluginManager' => Mvc\Factory\PaginatorPluginManagerFactory::class,
            'Request' => Mvc\Factory\RequestFactory::class,
            'Response' => Mvc\Factory\ResponseFactory::class,
            'ViewHelperManager' => Mvc\Factory\ViewHelperManagerFactory::class,
            Mvc\View\Http\DefaultRenderingStrategy::class => HttpDefaultRenderingStrategyFactory::class,
            'ViewFeedStrategy' => Mvc\Factory\ViewFeedStrategyFactory::class,
            'ViewManager' => Mvc\Factory\ViewManagerFactory::class,
            'ViewResolver' => Mvc\Factory\ViewResolverFactory::class,
            'ViewTemplateMapResolver' => Mvc\Factory\ViewTemplateMapResolverFactory::class,
            'ViewTemplatePathStack' => Mvc\Factory\ViewTemplatePathStackFactory::class,
            'ViewPrefixPathStackResolver' => Mvc\Factory\ViewPrefixPathStackResolverFactory::class,
            Mvc\RouteListener::class => InvokableFactory::class,
            Mvc\SendResponseListener::class => SendResponseListenerFactory::class,
            PhpRenderer::class => ViewPhpRendererFactory::class,
            View\View::class => ViewFactory::class,
        ],
    ];

    /**
     * Create the service listener service
     *
     * Tries to get a service named ServiceListenerInterface from the service
     * locator, otherwise creates a ServiceListener instance, passing it the
     * container instance and the default service configuration, which can be
     * overridden by modules.
     *
     * It looks for the 'service_listener_options' key in the application
     * config and tries to add service/plugin managers as configured. The value
     * of 'service_listener_options' must be a list (array) which contains the
     * following keys:
     *
     * - service_manager: the name of the service manage to create as string
     * - config_key: the name of the configuration key to search for as string
     * - interface: the name of the interface that modules can implement as string
     * - method: the name of the method that modules have to implement as string
     *
     * @param string $requestedName
     * @param null|array $options
     * @return ServiceListenerInterface
     * @throws ServiceNotCreatedException For invalid ServiceListener service.
     * @throws ServiceNotCreatedException For invalid configurations.
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ServiceListenerInterface {
        $configuration = $container->get('ApplicationConfig');

        /** @var mixed $serviceListener */
        $serviceListener = $container->has('ServiceListenerInterface')
            ? $container->get('ServiceListenerInterface')
            : new ServiceListener(Value::instance(
                $container,
                ServiceManager::class,
            ));

        if (!$serviceListener instanceof ServiceListenerInterface) {
            throw new ServiceNotCreatedException('The service named ServiceListenerInterface must implement '
            . ServiceListenerInterface::class);
        }

        $serviceListener->setDefaultServiceConfig($this->defaultServiceConfig);

        if (isset($configuration['service_listener_options'])) {
            $this->injectServiceListenerOptions(
                Value::array($configuration['service_listener_options']),
                $serviceListener,
            );
        }

        return $serviceListener;
    }

    /**
     * Validate and inject plugin manager options into the service listener.
     *
     * @param mixed $options
     * @throws ServiceListenerInterface For invalid $options types.
     */
    private function injectServiceListenerOptions($options, ServiceListenerInterface $serviceListener): void
    {
        if (!is_array($options)) {
            throw new ServiceNotCreatedException(sprintf(
                'The value of service_listener_options must be an array, %s given.',
                get_debug_type($options),
            ));
        }

        foreach ($options as $key => $newServiceManager) {
            $this->validatePluginManagerOptions(
                Value::array($newServiceManager),
                Value::string($key),
            );

            $serviceListener->addServiceManager(
                Value::string($newServiceManager['service_manager']),
                Value::string($newServiceManager['config_key']),
                Value::string($newServiceManager['interface']),
                Value::string($newServiceManager['method']),
            );
        }
    }

    /**
     * Validate the structure and types for plugin manager configuration options.
     *
     * Ensures all required keys are present in the expected types.
     *
     * @param mixed $options
     * @param string $name Plugin manager service name; used for exception messages
     * @throws ServiceNotCreatedException For any missing configuration options.
     * @throws ServiceNotCreatedException For configuration options of invalid types.
     */
    private function validatePluginManagerOptions($options, $name): void
    {
        if (!is_array($options)) {
            throw new ServiceNotCreatedException(sprintf(
                'Plugin manager configuration for "%s" is invalid; must be an array, received "%s"',
                $name,
                get_debug_type($options),
            ));
        }

        if (!isset($options['service_manager'])) {
            throw new ServiceNotCreatedException(sprintf(self::MISSING_KEY_ERROR, $name, 'service_manager'));
        }

        if (!is_string($options['service_manager'])) {
            throw new ServiceNotCreatedException(sprintf(
                self::VALUE_TYPE_ERROR,
                'service_manager',
                gettype($options['service_manager']),
            ));
        }

        if (!isset($options['config_key'])) {
            throw new ServiceNotCreatedException(sprintf(self::MISSING_KEY_ERROR, $name, 'config_key'));
        }

        if (!is_string($options['config_key'])) {
            throw new ServiceNotCreatedException(sprintf(
                self::VALUE_TYPE_ERROR,
                'config_key',
                gettype($options['config_key']),
            ));
        }

        if (!isset($options['interface'])) {
            throw new ServiceNotCreatedException(sprintf(self::MISSING_KEY_ERROR, $name, 'interface'));
        }

        if (!is_string($options['interface'])) {
            throw new ServiceNotCreatedException(sprintf(
                self::VALUE_TYPE_ERROR,
                'interface',
                gettype($options['interface']),
            ));
        }

        if (!isset($options['method'])) {
            throw new ServiceNotCreatedException(sprintf(self::MISSING_KEY_ERROR, $name, 'method'));
        }

        if (!is_string($options['method'])) {
            throw new ServiceNotCreatedException(sprintf(
                self::VALUE_TYPE_ERROR,
                'method',
                gettype($options['method']),
            ));
        }
    }
}
