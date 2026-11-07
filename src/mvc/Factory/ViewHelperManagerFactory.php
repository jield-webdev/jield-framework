<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Closure;
use Jield\Mvc\ApplicationInterface;
use Jield\Mvc\Router\RouteMatch;
use Jield\Mvc\View\Helper\Identity;
use Jield\Mvc\View\Helper\ServerUrl;
use Jield\Mvc\View\Helper\Translate;
use Jield\Mvc\View\Helper\Url;
use Jield\Mvc\View\Renderer\PhpRenderer;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Authentication\AuthenticationService;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\Stdlib\RequestInterface;
use Laminas\Translator\TranslatorInterface;
use Laminas\View\Factory\Configuration;
use Laminas\View\Helper as ViewHelper;
use Laminas\View\Helper\BasePath;
use Laminas\View\Helper\Doctype;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

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
        Value::setPath($options, ['initializers', null], static function (
            ContainerInterface $pluginContainer,
            mixed $helper,
        ) use ($container): void {
            if (method_exists(Value::objectOrString($helper), 'setView')) {
                Value::call($helper, 'setView', [Service::get($container, PhpRenderer::class)]);
            }
            if (method_exists(Value::objectOrString($helper), 'setTranslator')) {
                Value::call($helper, 'setTranslator', [Service::get($container, TranslatorInterface::class)]);
            }
        });
        $plugins = parent::__invoke($container, $requestedName, $options);

        // Override plugin factories
        return $this->injectOverrideFactories(
            Assert::isInstanceOf($plugins, \Laminas\View\HelperPluginManager::class),
            $container,
        );
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
        $plugins->configure(['factories' => [
            Url::class => $this->createUrlHelperFactory($services),
        ]]);
        $plugins->configure(['factories' => [
            ServerUrl::class => static fn() => new ServerUrl(),
        ]]);
        $plugins->configure(['factories' => [
            Identity::class => static fn() => new Identity(Service::get($services, AuthenticationService::class)),
        ]]);
        $plugins->configure(['factories' => [
            Translate::class => static fn() => new Translate(),
        ]]);
        $plugins->configure(['aliases' => [
            'url' => Url::class,
            'serverUrl' => ServerUrl::class,
            'serverurl' => ServerUrl::class,
            'identity' => Identity::class,
            'translate' => Translate::class,
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
            $helperClass = Url::class;
            $helper = new $helperClass();
            $helper->setRouter($services->get('HttpRouter'));
            $match = Service::get(
                $services,
                ApplicationInterface::class,
                'Application',
            )->getMvcEvent()->getRouteMatch();
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
    private function createBasePathHelperFactory(ContainerInterface $services): callable|Closure
    {
        return static function () use ($services): BasePath {
            $config = Configuration::get($services);
            /** @mago-var mixed $basePath */
            $basePath = $config['view_manager']['base_path'] ?? null;
            /** @mago-var mixed $basePath */
            $basePath = $config['view_helper_config']['base_path'] ?? $basePath;

            $helper = new ViewHelper\BasePath(Assert::nullOrString($basePath));
            if ($basePath !== null) {
                return $helper;
            }

            $request = Service::get($services, RequestInterface::class, 'Request');
            if (is_callable([$request, 'getBasePath'])) {
                $helper->setBasePath(Assert::string(Value::call($request, 'getBasePath')));
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
    private function createDoctypeHelperFactory(ContainerInterface $services): callable|Closure
    {
        return static function () use ($services): Doctype {
            $config = Configuration::get($services);
            /** @mago-var mixed $doctype */
            $doctype = $config['view_manager']['doctype'] ?? Doctype::DEFAULT_DOCTYPE;
            /** @mago-var mixed $doctype */
            $doctype = $config['view_helper_config']['doctype'] ?? $doctype;

            return new ViewHelper\Doctype(Value::key($doctype));
        };
    }
}
