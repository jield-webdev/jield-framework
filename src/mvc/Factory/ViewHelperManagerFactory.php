<?php

namespace Jield\Mvc\Factory;

use Laminas\Router\RouteMatch;
use Laminas\View\Helper as ViewHelper;
use Laminas\View\Helper\BasePath;
use Laminas\View\Helper\Doctype;
use Laminas\View\HelperPluginManager;
use Laminas\View\Factory\Configuration;
use Psr\Container\ContainerInterface;
use function is_callable;

class ViewHelperManagerFactory extends AbstractPluginManagerFactory
{
    public const string PLUGIN_MANAGER_CLASS = HelperPluginManager::class;

    /**
     * An array of helper configuration classes to ensure are on the helper_map stack.
     *
     * These are *not* imported; that way they can be optional dependencies.
     *
     * @todo Remove these once their components have Modules defined.
     * @var array
     */
    protected array $defaultHelperMapClasses = [];

    /**
     * Create and return the view helper manager
     *
     * @param string $requestedName
     * @param  null|array         $options
     * @return HelperPluginManager
     * @throws ServiceNotCreatedException
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): HelperPluginManager
    {
        $options                = $options ?: [];
        $options['factories'] ??= [];
        $plugins                = parent::__invoke($container, $requestedName, $options);

        // Override plugin factories
        $plugins = $this->injectOverrideFactories($plugins, $container);

        return $plugins;
    }

    /**
     * Inject override factories into the plugin manager.
     *
     * @return HelperPluginManager
     */
    private function injectOverrideFactories(HelperPluginManager $plugins, ContainerInterface $services): HelperPluginManager
    {
        if (class_exists(ViewHelper\Url::class)) {
            $urlFactory = $this->createUrlHelperFactory($services);
            $plugins->setFactory(ViewHelper\Url::class, $urlFactory);
            $plugins->setFactory('laminasviewhelperurl', $urlFactory);
        }

        // Configure base path helper
        $basePathFactory = $this->createBasePathHelperFactory($services);
        $plugins->setFactory(ViewHelper\BasePath::class, $basePathFactory);
        $plugins->setFactory('laminasviewhelperbasepath', $basePathFactory);

        // Configure doctype view helper
        $doctypeFactory = $this->createDoctypeHelperFactory($services);
        $plugins->setFactory(ViewHelper\Doctype::class, $doctypeFactory);
        $plugins->setFactory('laminasviewhelperdoctype', $doctypeFactory);

        return $plugins;
    }

    /**
     * Create and return a factory for creating a URL helper.
     *
     * Retrieves the application and router from the servicemanager,
     * and the route match from the MvcEvent composed by the application,
     * using them to configure the helper.
     *
     * @return callable
     */
    private function createUrlHelperFactory(ContainerInterface $services): callable
    {
        return static function () use ($services): object {
            $helperClass = ViewHelper\Url::class;
            $helper      = new $helperClass();
            $helper->setRouter($services->get('HttpRouter'));
            $match = $services->get('Application')
                ->getMvcEvent()
                ->getRouteMatch();
            if ($match instanceof RouteMatch) {
                $helper->setRouteMatch($match);
            }
            return $helper;
        };
    }

    /**
     * Create and return a factory for creating a BasePath helper.
     *
     * Uses configuration and request services to configure the helper.
     *
     * @return callable
     */
    private function createBasePathHelperFactory(ContainerInterface $services): callable|\Closure
    {
        return static function () use ($services): BasePath {
            $config   = Configuration::get($services);
            $basePath = $config['view_manager']['base_path'] ?? null;
            $basePath = $config['view_helper_config']['base_path'] ?? $basePath;

            $helper = new ViewHelper\BasePath($basePath);
            if ($basePath !== null) {
                return $helper;
            }

            $request = $services->get('Request');
            if (is_callable([$request, 'getBasePath'])) {
                $helper->setBasePath($request->getBasePath());
            }
            return $helper;
        };
    }

    /**
     * Create and return a Doctype helper factory.
     *
     * Other view helpers depend on this to decide which spec to generate their tags
     * based on. This is why it must be set early instead of later in the layout phtml.
     *
     * @return callable
     */
    private function createDoctypeHelperFactory(ContainerInterface $services): callable|\Closure
    {
        return static function () use ($services): Doctype {
            $config  = Configuration::get($services);
            $doctype = $config['view_manager']['doctype'] ?? Doctype::DEFAULT_DOCTYPE;
            $doctype = $config['view_helper_config']['doctype'] ?? $doctype;

            return new ViewHelper\Doctype($doctype);
        };
    }
}
