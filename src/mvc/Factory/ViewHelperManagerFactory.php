<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Jield\Mvc\Router\RouteMatch;
use Laminas\View\Factory\Configuration;
use Laminas\View\Helper as ViewHelper;
use Laminas\View\Helper\BasePath;
use Laminas\View\Helper\Doctype;
use Laminas\View\HelperPluginManager;
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
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): HelperPluginManager {
        $options = $options ?: [];
        $options['factories'] ??= [];
        \Jield\Stdlib\Value::setPath($options, ['initializers', null], static function (ContainerInterface $pluginContainer, mixed $helper) use (
            $container,
        ): void {
            if (method_exists(\Jield\Stdlib\Value::objectOrString($helper), 'setView')) {
                \Jield\Stdlib\Value::call($helper, 'setView', [\Jield\Stdlib\Service::get($container, \Jield\Mvc\View\Renderer\PhpRenderer::class)]);
            }
            if (method_exists(\Jield\Stdlib\Value::objectOrString($helper), 'setTranslator')) {
                \Jield\Stdlib\Value::call($helper, 'setTranslator', [\Jield\Stdlib\Service::get($container, \Laminas\Translator\TranslatorInterface::class)]);
            }
        });
        $plugins = parent::__invoke($container, $requestedName, $options);

        // Override plugin factories
        return $this->injectOverrideFactories(\Jield\Stdlib\Value::instance($plugins, \Laminas\View\HelperPluginManager::class), $container);
    }

    /**
     * Inject override factories into the plugin manager.
     *
     * @return HelperPluginManager
     */
    private function injectOverrideFactories(
        HelperPluginManager $plugins,
        ContainerInterface $services,
    ): HelperPluginManager {
        $plugins->configure(['factories' => [\Jield\Mvc\View\Helper\Url::class => $this->createUrlHelperFactory($services)]]);
        $plugins->configure(['factories' => [\Jield\Mvc\View\Helper\ServerUrl::class => static fn() => new \Jield\Mvc\View\Helper\ServerUrl()]]);
        $plugins->configure(['factories' => [\Jield\Mvc\View\Helper\Identity::class => static fn() => new \Jield\Mvc\View\Helper\Identity(\Jield\Stdlib\Service::get($services, \Laminas\Authentication\AuthenticationService::class))]]);
        $plugins->configure(['factories' => [\Jield\Mvc\View\Helper\Translate::class => static fn() => new \Jield\Mvc\View\Helper\Translate()]]);
        $plugins->configure(['aliases' => [
            'url' => \Jield\Mvc\View\Helper\Url::class,
            'serverUrl' => \Jield\Mvc\View\Helper\ServerUrl::class,
            'serverurl' => \Jield\Mvc\View\Helper\ServerUrl::class,
            'identity' => \Jield\Mvc\View\Helper\Identity::class,
            'translate' => \Jield\Mvc\View\Helper\Translate::class,
        ]]);

        // Configure base path helper
        $basePathFactory = $this->createBasePathHelperFactory($services);
        $plugins->configure(['factories' => [ViewHelper\BasePath::class => $basePathFactory]]);
        $plugins->configure(['factories' => ['laminasviewhelperbasepath' => $basePathFactory]]);

        // Configure doctype view helper
        $doctypeFactory = $this->createDoctypeHelperFactory($services);
        $plugins->configure(['factories' => [ViewHelper\Doctype::class => $doctypeFactory]]);
        $plugins->configure(['factories' => ['laminasviewhelperdoctype' => $doctypeFactory]]);

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
            $helperClass = \Jield\Mvc\View\Helper\Url::class;
            $helper = new $helperClass();
            $helper->setRouter($services->get('HttpRouter'));
            $match = \Jield\Stdlib\Service::get($services, \Jield\Mvc\ApplicationInterface::class, 'Application')->getMvcEvent()->getRouteMatch();
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
            $config = Configuration::get($services);
            /** @var mixed $basePath */
            $basePath = $config['view_manager']['base_path'] ?? null;
            /** @var mixed $basePath */
            $basePath = $config['view_helper_config']['base_path'] ?? $basePath;

            $helper = new ViewHelper\BasePath(\Jield\Stdlib\Value::nullableString($basePath));
            if ($basePath !== null) {
                return $helper;
            }

            $request = \Jield\Stdlib\Service::get($services, \Laminas\Stdlib\RequestInterface::class, 'Request');
            if (is_callable([$request, 'getBasePath'])) {
                $helper->setBasePath(\Jield\Stdlib\Value::string(\Jield\Stdlib\Value::call($request, 'getBasePath')));
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
            $config = Configuration::get($services);
            /** @var mixed $doctype */
            $doctype = $config['view_manager']['doctype'] ?? Doctype::DEFAULT_DOCTYPE;
            /** @var mixed $doctype */
            $doctype = $config['view_helper_config']['doctype'] ?? $doctype;

            return new ViewHelper\Doctype(\Jield\Stdlib\Value::key($doctype));
        };
    }
}
