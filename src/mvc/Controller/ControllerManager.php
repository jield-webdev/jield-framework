<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller;

// phpcs:ignore
use Jield\Stdlib\Service;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\DispatchableInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function get_debug_type;
use function method_exists;
use function sprintf;

/**
 * Manager for loading controllers
 *
 * Does not define any controllers by default, but does add a validator.
 *
 *
 * @extends \Laminas\ServiceManager\AbstractPluginManager<\Laminas\Stdlib\DispatchableInterface>
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
class ControllerManager extends AbstractPluginManager
{
    protected bool $autoAddInvokableClass = false;

    protected string $instanceOf = DispatchableInterface::class;

    public function __construct(ContainerInterface $configOrContainerInstance, array $config = [])
    {
        $initializers = [
            $this->injectEventManager(...),
            $this->injectPluginManager(...),
        ];

        if (isset($config['initializers'])) {
            $config['initializers'] = [...Assert::isArray($config['initializers']), ...$initializers];
        } else {
            $config['initializers'] = $initializers;
        }

        /** @var ServiceManagerConfiguration $managerConfiguration */
        $managerConfiguration = $config;
        parent::__construct($configOrContainerInstance, $managerConfiguration);
    }

    /**
     * Validate a plugin
     *
     * {@inheritDoc}
     */
    public function validate($instance): void
    {
        if (!$instance instanceof $this->instanceOf) {
            throw new InvalidServiceException(sprintf(
                'Plugin of type "%s" is invalid; must implement %s',
                get_debug_type($instance),
                $this->instanceOf,
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
        if (!$events->getSharedManager() instanceof SharedEventManagerInterface) {
            $controller->setEventManager(Service::get($container, EventManagerInterface::class, 'EventManager'));
        }
    }

    /**
     * Initializer: inject plugin manager
     *
     * @param DispatchableInterface $controller
     */
    public function injectPluginManager(ContainerInterface $container, $controller): void
    {
        if (!method_exists($controller, 'setPluginManager')) {
            return;
        }

        $controller->setPluginManager(Service::get(
            $container,
            \Jield\Mvc\Controller\PluginManager::class,
            'ControllerPluginManager',
        ));
    }
}
