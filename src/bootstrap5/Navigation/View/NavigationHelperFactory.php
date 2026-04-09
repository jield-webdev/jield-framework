<?php

namespace Jield\Bootstrap5\Navigation\View;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Jield\Bootstrap5\View\Helper\Navigation;
use Psr\Container\ContainerInterface;

/**
 * Class NavigationHelperFactory
 * @package Jield\Bootstrap5\Navigation\View
 */
final class NavigationHelperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Navigation
    {
        $helper = new Navigation();
        $helper->setServiceLocator($container);

        return $helper;
    }
}
