<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Laminas\Router\RouteMatch;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\View\Helper as ViewHelper;
use Laminas\View\Helper\BasePath;
use Laminas\View\Helper\Doctype;
use Laminas\View\Helper\Url;
use Laminas\View\HelperPluginManager;

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
     * @param  string             $requestedName
     * @param  null|array         $options
     * @return HelperPluginManager
     * @throws ServiceNotCreatedException
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): HelperPluginManager
    {
        $options                = $options ?: [];
        $options['factories'] ??= [];
        $plugins                = parent::__invoke(container: $container, requestedName: $requestedName, options: $options);

        // Override plugin factories
        $plugins = $this->injectOverrideFactories(plugins: $plugins, services: $container);

        return $plugins;
    }

    /**
     * Inject override factories into the plugin manager.
     *
     * @return HelperPluginManager
     */
    private function injectOverrideFactories(HelperPluginManager $plugins, ContainerInterface $services): HelperPluginManager
    {
        // Configure URL view helper
        $urlFactory = $this->createUrlHelperFactory(services: $services);
        $plugins->setFactory(name: ViewHelper\Url::class, factory: $urlFactory);
        $plugins->setFactory(name: 'jieldviewhelperurl', factory: $urlFactory);

        // Configure base path helper
        $basePathFactory = $this->createBasePathHelperFactory(services: $services);
        $plugins->setFactory(name: ViewHelper\BasePath::class, factory: $basePathFactory);
        $plugins->setFactory(name: 'jieldviewhelperbasepath', factory: $basePathFactory);

        // Configure doctype view helper
        $doctypeFactory = $this->createDoctypeHelperFactory(services: $services);
        $plugins->setFactory(name: ViewHelper\Doctype::class, factory: $doctypeFactory);
        $plugins->setFactory(name: 'jieldviewhelperdoctype', factory: $doctypeFactory);

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
    private function createUrlHelperFactory(ContainerInterface $services): callable|\Closure
    {
        return static function () use ($services): Url {
            $helper = new ViewHelper\Url();
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
            $config = $services->has(id: 'config') ? $services->get('config') : [];
            $helper = new ViewHelper\BasePath();
            if (isset($config['view_manager']) && isset($config['view_manager']['base_path'])) {
                $helper->setBasePath(basePath: $config['view_manager']['base_path']);
                return $helper;
            }
            $request = $services->get('Request');
            if (is_callable(value: [$request, 'getBasePath'])) {
                $helper->setBasePath(basePath: $request->getBasePath());
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
            $config = $services->has(id: 'config') ? $services->get('config') : [];
            $config = $config['view_manager'] ?? [];
            $helper = new ViewHelper\Doctype();
            if (isset($config['doctype']) && $config['doctype']) {
                $helper->setDoctype($config['doctype']);
            }
            return $helper;
        };
    }
}
