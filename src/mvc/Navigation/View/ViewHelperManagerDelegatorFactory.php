<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\View;

use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;

use function assert;

/**
 * Inject the view HelperPluginManager with the Jield navigation view helper
 * configuration.
 *
 * The {@see HelperConfig} class ensures the navigation proxy helper and all its
 * sub-helpers are registered and injected with the application container.
 *
 * @final
 */
class ViewHelperManagerDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        ?array $options = null,
    ): HelperPluginManager {
        /** @mago-var mixed $viewHelpers */
        $viewHelpers = $callback();
        assert($viewHelpers instanceof HelperPluginManager);

        new HelperConfig()->configureServiceManager($viewHelpers, $container);

        return $viewHelpers;
    }
}
