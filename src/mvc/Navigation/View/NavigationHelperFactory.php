<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\View;

use Jield\Mvc\Navigation\View\Navigation as NavigationHelper;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * @final
 */
class NavigationHelperFactory implements FactoryInterface
{
    /**
     * Create and return a navigation proxy helper instance.
     *
     * When invoked from within the view helper plugin manager, the provided
     * container is that manager's creation context, i.e. the application
     * container. That is exactly what the navigation helpers need as their
     * service locator, so it can be injected directly.
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): NavigationHelper {
        $helper = new NavigationHelper();
        $helper->setServiceLocator($container);

        return $helper;
    }
}
