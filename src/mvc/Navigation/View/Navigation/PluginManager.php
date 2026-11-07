<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\View\Navigation;

use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Renderer\RendererInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function get_debug_type;
use function sprintf;

/**
 * Plugin manager implementation for navigation helpers.
 *
 * Enforces that helpers retrieved are instances of {@see AbstractHelper}, and
 * registers the default navigation helpers.
 *
 * Service Manager 4 made {@see \Laminas\View\HelperPluginManager} final and
 * replaced the `$instanceOf` property with the {@see self::validate()} hook, so
 * this manager extends {@see AbstractPluginManager} directly instead.
 *
 * @template-extends AbstractPluginManager<AbstractHelper>
 * @psalm-import-type FactoriesConfiguration from ServiceManager
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
final class PluginManager extends AbstractPluginManager
{
    /**
     * Default aliases.
     *
     * @var array<string, class-string<AbstractHelper>>
     */
    private ?RendererInterface $renderer = null;

    public function setRenderer(RendererInterface $renderer): void
    {
        $this->renderer = $renderer;
    }

    protected array $aliases = [
        'breadcrumbs' => Breadcrumbs::class,
        'links' => Links::class,
        'menu' => Menu::class,
    ];

    /**
     * Default factories.
     *
     * @var FactoriesConfiguration
     */
    protected array $factories = [
        Breadcrumbs::class => InvokableFactory::class,
        Links::class => InvokableFactory::class,
        Menu::class => InvokableFactory::class,
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(ContainerInterface $creationContext, array $config = [])
    {
        // Inject the application container into every navigation helper. Under
        // Service Manager 4 the initializer's container argument *is* the
        // creation context passed to the parent constructor below.
        $initializers = Assert::isArray($config['initializers'] ?? []);
        $initializers[] = function (ContainerInterface $container, mixed $instance): void {
            if ($instance instanceof AbstractHelper) {
                $instance->setServiceLocator($container);
                if ($this->renderer !== null) {
                    $instance->setView($this->renderer);
                }
            }
        };

        $config['initializers'] = $initializers;
        /** @var ServiceManagerConfiguration $managerConfiguration */
        $managerConfiguration = $config;
        parent::__construct($creationContext, $managerConfiguration);
    }

    /**
     * Validate that a retrieved plugin is a navigation helper.
     *
     * @psalm-assert AbstractHelper $instance
     * @throws InvalidServiceException When the instance is not an {@see AbstractHelper}.
     */
    public function validate(mixed $instance): void
    {
        if ($instance instanceof AbstractHelper) {
            return;
        }

        throw new InvalidServiceException(sprintf(
            '%s can only create instances of %s; %s is invalid',
            self::class,
            AbstractHelper::class,
            get_debug_type($instance),
        ));
    }
}
