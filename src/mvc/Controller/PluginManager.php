<?php

namespace Jield\Mvc\Controller;

use Jield\Mvc\Controller\Plugin\AcceptableViewModelSelector;
use Jield\Mvc\Controller\Plugin\CreateHttpNotFoundModel;
use Jield\Mvc\Controller\Plugin\Forward;
use Jield\Mvc\Controller\Plugin\Layout;
use Jield\Mvc\Controller\Plugin\Params;
use Jield\Mvc\Controller\Plugin\PluginInterface;
use Jield\Mvc\Controller\Plugin\Redirect;
use Jield\Mvc\Controller\Plugin\Service\ForwardFactory;
use Jield\Mvc\Controller\Plugin\Url;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\Stdlib\DispatchableInterface;
use function get_debug_type;
use function method_exists;
use function sprintf;

/**
 * Plugin manager implementation for controllers
 *
 * Registers a number of default plugins, and contains an initializer for
 * injecting plugins with the current controller.
 */
class PluginManager extends AbstractPluginManager
{
    /**
     * Plugins must be of this type.
     *
     * @var string
     */
    protected string $instanceOf = PluginInterface::class;

    /** @var string[] Default aliases */
    protected array $aliases
        = [
            'AcceptableViewModelSelector' => AcceptableViewModelSelector::class,
            'acceptableViewModelSelector' => AcceptableViewModelSelector::class,
            'Forward'                     => Forward::class,
            'forward'                     => Forward::class,
            'Layout'                      => Layout::class,
            'layout'                      => Layout::class,
            'Params'                      => Params::class,
            'params'                      => Params::class,
            'Redirect'                    => Redirect::class,
            'redirect'                    => Redirect::class,
            'Url'                         => Url::class,
            'url'                         => Url::class,
            'CreateHttpNotFoundModel'     => CreateHttpNotFoundModel::class,
            'createHttpNotFoundModel'     => CreateHttpNotFoundModel::class,
        ];

    /** @var string[]|callable[] Default factories */
    protected array $factories
        = [
            Forward::class                     => ForwardFactory::class,
            AcceptableViewModelSelector::class => InvokableFactory::class,
            Layout::class                      => InvokableFactory::class,
            Params::class                      => InvokableFactory::class,
            Redirect::class                    => InvokableFactory::class,
            Url::class                         => InvokableFactory::class,
            CreateHttpNotFoundModel::class     => InvokableFactory::class,
        ];

    protected ?DispatchableInterface $controller = null;

    /**
     * Retrieve a registered instance
     *
     * After the plugin is retrieved from the service locator, inject the
     * controller in the plugin every time it is requested. This is required
     * because a controller can use a plugin and another controller can be
     * dispatched afterwards. If this second controller uses the same plugin
     * as the first controller, the reference to the controller inside the
     * plugin is lost.
     *
     * @param string $id
     * @param null|array $options Options to use when creating the instance.
     * @return DispatchableInterface
     */
    public function get($id, ?array $options = null): DispatchableInterface
    {
        $plugin = parent::get($id, $options);
        $this->injectController(plugin: $plugin);

        return $plugin;
    }

    /**
     * Set controller
     *
     * @return PluginManager
     */
    public function setController(DispatchableInterface $controller): static
    {
        $this->controller = $controller;

        return $this;
    }

    /**
     * Retrieve controller instance
     *
     * @return null|DispatchableInterface
     */
    public function getController(): ?DispatchableInterface
    {
        return $this->controller;
    }

    /**
     * Inject a helper instance with the registered controller
     *
     * @param object $plugin
     * @return void
     */
    public function injectController(object $plugin): void
    {
        if (!method_exists(object_or_class: $plugin, method: 'setController')) {
            return;
        }

        $controller = $this->getController();
        if (!$controller instanceof DispatchableInterface) {
            return;
        }

        $plugin->setController($controller);
    }

    /**
     * Validate a plugin
     *
     * {@inheritDoc}
     */
    public function validate($instance): void
    {
        if (!$instance instanceof $this->instanceOf) {
            throw new InvalidServiceException(message: sprintf(
                'Plugin of type "%s" is invalid; must implement %s',
                get_debug_type(value: $instance),
                $this->instanceOf
            ));
        }
    }
}
