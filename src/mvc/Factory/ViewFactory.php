<?php

namespace Jield\Mvc\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Factory\Configuration;
use Laminas\View\HelperPluginManagerInterface;
use Laminas\View\Renderer\RendererInterface;
use Laminas\View\View;
use Psr\Container\ContainerInterface;

class ViewFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param null|array $options
     * @return View
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): View
    {
        return new View(
            $container->get(RendererInterface::class),
            $container->get(HelperPluginManagerInterface::class),
            Configuration::defaultLayout($container),
            Configuration::defaultCaptureTo($container),
        );
    }
}
