<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Twig\ModuleOptions;
use Jield\Twig\View\HelperPluginManager;
use Jield\Twig\View\TwigRenderer;
use Jield\Twig\View\TwigResolver;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\View;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use Twig\Loader\ChainLoader;

class TwigRendererFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): TwigRenderer
    {
        if (null === $options) {
            /** @var ModuleOptions $options */
            $options = $container->get(ModuleOptions::class);
        }

        $renderer = new TwigRenderer(
            $container->get(View::class),
            $container->get(ChainLoader::class),
            $container->get(Environment::class),
            $container->get(TwigResolver::class)
        );

        $renderer->setCanRenderTrees(! $options->getDisableZfModel());
        $renderer->setHelperPluginManager($container->get(HelperPluginManager::class));

        return $renderer;
    }
}
