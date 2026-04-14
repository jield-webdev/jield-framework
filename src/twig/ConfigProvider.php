<?php

declare(strict_types=1);

namespace Jield\Twig;

use Jield\Mvc\Plugin\FlashMessenger\Factory\FlashMessengerFactory;
use Jield\Mvc\Plugin\FlashMessenger\View\Helper\FlashMessenger as FlashMessengerHelper;
use Jield\Twig\Factory\ChainLoaderFactory;
use Jield\Twig\Factory\EnvironmentFactory;
use Jield\Twig\Factory\ExtensionFactory;
use Jield\Twig\Factory\MapLoaderFactory;
use Jield\Twig\Factory\ModuleOptionsFactory;
use Jield\Twig\Factory\StackLoaderFactory;
use Jield\Twig\Factory\TwigRendererFactory;
use Jield\Twig\Factory\TwigResolverFactory;
use Jield\Twig\Factory\TwigStrategyFactory;
use Laminas\Navigation\View\HelperConfig;
use Twig\Environment;
use Twig\Loader;
use Twig\Loader\ChainLoader;

final class ConfigProvider
{
    public function __invoke(): array
    {
        $dependencies = $this->getDependencies();

        return [
            'jield_twig'      => $this->getJieldTwigConfig(),
            'view_helpers'    => $this->getViewHelperConfig(),
            'view_manager'    => $this->getViewManagerConfig(),
            'dependencies'    => $dependencies,
            'service_manager' => $dependencies,
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                'JieldTwigExtension'               => Extension::class,
                'JieldTwigLoaderChain'             => Loader\ChainLoader::class,
                'JieldTwigLoaderTemplateMap'       => MapLoader::class,
                'JieldTwigLoaderTemplatePathStack' => StackLoader::class,
                'JieldTwigRenderer'                => View\TwigRenderer::class,
                'JieldTwigResolver'                => View\TwigResolver::class,
                'JieldTwigViewStrategy'            => View\TwigStrategy::class,
            ],
            'factories' => [
                Environment::class       => EnvironmentFactory::class,
                ChainLoader::class       => ChainLoaderFactory::class,
                Extension::class         => ExtensionFactory::class,
                MapLoader::class         => MapLoaderFactory::class,
                StackLoader::class       => StackLoaderFactory::class,
                View\TwigRenderer::class => TwigRendererFactory::class,
                View\TwigResolver::class => TwigResolverFactory::class,
                View\TwigStrategy::class => TwigStrategyFactory::class,
                ModuleOptions::class     => ModuleOptionsFactory::class,
            ],
        ];
    }

    public function getJieldTwigConfig(): array
    {
        return [
            'environment_loader'        => ChainLoader::class,
            'environment_class'         => Environment::class,
            'environment_options'       => [],
            'loader_chain'              => [
                MapLoader::class,
                StackLoader::class,
            ],
            'extensions'                => [
                Extension::class,
            ],
            'suffix'                    => 'twig',
            'enable_fallback_functions' => true,
            'helper_manager'            => [
                'configs' => [
                    HelperConfig::class,
                ],
            ],
        ];
    }

    public function getViewHelperConfig(): array
    {
        return [
            'aliases'   => [
                'flashmessenger' => FlashMessengerHelper::class,
                'flashMessenger' => FlashMessengerHelper::class,
                'FlashMessenger' => FlashMessengerHelper::class,
            ],
            'factories' => [
                FlashMessengerHelper::class => FlashMessengerFactory::class,
            ],
        ];
    }

    public function getViewManagerConfig(): array
    {
        return [
            'strategies' => [View\TwigStrategy::class],
        ];
    }
}
