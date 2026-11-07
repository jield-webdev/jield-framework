<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Stdlib\Service;
use Jield\Twig\View\TwigRenderer;
use Jield\Twig\View\TwigResolver;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\HelperPluginManager;
use Laminas\View\View;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use Twig\Loader\ChainLoader;

class TwigRendererFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): TwigRenderer
    {
        $renderer = new TwigRenderer(
            Service::get($container, View::class),
            Service::get($container, ChainLoader::class),
            Service::get($container, Environment::class),
            Service::get($container, TwigResolver::class),
        );

        $renderer->setCanRenderTrees(true);
        $renderer->setHelperPluginManager(Service::get($container, HelperPluginManager::class));

        return $renderer;
    }
}
