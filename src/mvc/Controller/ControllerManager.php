<?php

namespace Jield\Mvc\Controller;

// phpcs:ignore
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\ConfigInterface;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Jield\Stdlib\DispatchableInterface;
use Psr\Container\ContainerInterface;
use function get_debug_type;
use function method_exists;
use function sprintf;

/**
 * Manager for loading controllers
 *
 * Does not define any controllers by default, but does add a validator.
 */
class ControllerManager extends AbstractPluginManager
{
    /**
     * We do not want arbitrary classes instantiated as controllers.
     *
     * @var bool
     */
    protected bool $autoAddInvokableClass = false;

    /**
     * Controllers must be of this type.
     *
     * @var string
     */
    protected string $instanceOf = DispatchableInterface::class;

    /**
     * Constructor
     *
     * Injects an initializer for injecting controllers with an
     * event manager and plugin manager.
     *
     * @param ConfigInterface|ContainerInterface $configOrContainerInstance
     */
    public function __construct($configOrContainerInstance, array $config = [])
    {
        $this->addInitializer(initializer: [$this, 'injectEventManager']);
        $this->addInitializer(initializer: [$this, 'injectPluginManager']);
        parent::__construct(creationContext: $configOrContainerInstance, config: $config);
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

    /**
     * Initializer: inject EventManager instance
     *
     * If we have an event manager composed already, make sure it gets injected
     * with the shared event manager.
     *
     * The AbstractController lazy-instantiates an EM instance, which is why
     * the shared EM injection needs to happen; the conditional will always
     * pass.
     *
     * @param DispatchableInterface $controller
     */
    public function injectEventManager(ContainerInterface $container, DispatchableInterface $controller): void
    {
        if (!$controller instanceof EventManagerAwareInterface) {
            return;
        }

        $events = $controller->getEventManager();
        if (!$events || !$events->getSharedManager() instanceof SharedEventManagerInterface) {
            $controller->setEventManager($container->get('EventManager'));
        }
    }

    /**
     * Initializer: inject plugin manager
     *
     * @param DispatchableInterface $controller
     */
    public function injectPluginManager(ContainerInterface $container, DispatchableInterface $controller): void
    {
        if (!method_exists(object_or_class: $controller, method: 'setPluginManager')) {
            return;
        }

        $controller->setPluginManager(plugins: $container->get('ControllerPluginManager'));
    }
}
