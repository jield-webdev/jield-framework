<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

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
            \Jield\Stdlib\Service::get($container, View::class),
            \Jield\Stdlib\Service::get($container, ChainLoader::class),
            \Jield\Stdlib\Service::get($container, Environment::class),
            \Jield\Stdlib\Service::get($container, TwigResolver::class),
        );

        $renderer->setCanRenderTrees(true);
        $renderer->setHelperPluginManager(\Jield\Stdlib\Service::get($container, HelperPluginManager::class));

        return $renderer;
    }
}
