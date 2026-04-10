<?php

use Jield\Twig\Extension;
use Jield\Twig\Factory\ChainLoaderFactory;
use Jield\Twig\Factory\EnvironmentFactory;
use Jield\Twig\Factory\ExtensionFactory;
use Jield\Twig\Factory\HelperPluginManagerFactory;
use Jield\Twig\Factory\MapLoaderFactory;
use Jield\Twig\Factory\ModuleOptionsFactory;
use Jield\Twig\Factory\StackLoaderFactory;
use Jield\Twig\Factory\TwigRendererFactory;
use Jield\Twig\Factory\TwigResolverFactory;
use Jield\Twig\Factory\TwigStrategyFactory;
use Jield\Twig\MapLoader;
use Jield\Twig\ModuleOptions;
use Jield\Twig\StackLoader;
use Jield\Twig\View;
use Twig\Environment;
use Twig\Loader;

return [
    'aliases' => [
        'JieldTwigExtension'               => Extension::class,
        'JieldTwigLoaderChain'             => Loader\ChainLoader::class,
        'JieldTwigLoaderTemplateMap'       => MapLoader::class,
        'JieldTwigLoaderTemplatePathStack' => StackLoader::class,
        'JieldTwigRenderer'                => View\TwigRenderer::class,
        'JieldTwigResolver'                => View\TwigResolver::class,
        'JieldTwigViewHelperManager'       => View\HelperPluginManager::class,
        'JieldTwigViewStrategy'            => View\TwigStrategy::class,
        'ZfcTwigExtension'                 => Extension::class,
        'ZfcTwigLoaderChain'               => Loader\ChainLoader::class,
        'ZfcTwigLoaderTemplateMap'         => MapLoader::class,
        'ZfcTwigLoaderTemplatePathStack'   => StackLoader::class,
        'ZfcTwigRenderer'                  => View\TwigRenderer::class,
        'ZfcTwigResolver'                  => View\TwigResolver::class,
        'ZfcTwigViewHelperManager'         => View\HelperPluginManager::class,
        'ZfcTwigViewStrategy'              => View\TwigStrategy::class,
    ],

    'factories' => [
        Environment::class            => EnvironmentFactory::class,
        Loader\ChainLoader::class     => ChainLoaderFactory::class,
        Extension::class              => ExtensionFactory::class,
        MapLoader::class              => MapLoaderFactory::class,
        StackLoader::class            => StackLoaderFactory::class,
        View\TwigRenderer::class      => TwigRendererFactory::class,
        View\TwigResolver::class      => TwigResolverFactory::class,
        View\HelperPluginManager::class => HelperPluginManagerFactory::class,
        View\TwigStrategy::class      => TwigStrategyFactory::class,
        ModuleOptions::class          => ModuleOptionsFactory::class,
    ]
];
