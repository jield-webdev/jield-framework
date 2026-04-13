<?php

namespace Jield\AssetManager;

use Jield\AssetManager\Command\WarmupCommand;

return [
    'service_manager'                                                    => [
        'factories'  => [
            \Jield\AssetManager\Service\AssetManager::class              => \Jield\AssetManager\Service\AssetManagerServiceFactory::class,
            \Jield\AssetManager\Service\AssetFilterManager::class        => \Jield\AssetManager\Service\AssetFilterManagerServiceFactory::class,
            \Jield\AssetManager\Service\AssetCacheManager::class         => \Jield\AssetManager\Service\AssetCacheManagerServiceFactory::class,
            \Jield\AssetManager\Resolver\AggregateResolver::class        => \Jield\AssetManager\Service\AggregateResolverServiceFactory::class,
            \Jield\AssetManager\Resolver\MapResolver::class              => \Jield\AssetManager\Service\MapResolverServiceFactory::class,
            \Jield\AssetManager\Resolver\PathStackResolver::class        => \Jield\AssetManager\Service\PathStackResolverServiceFactory::class,
            \Jield\AssetManager\Resolver\PrioritizedPathsResolver::class => \Jield\AssetManager\Service\PrioritizedPathsResolverServiceFactory::class,
            \Jield\AssetManager\Resolver\CollectionResolver::class       => \Jield\AssetManager\Service\CollectionResolverServiceFactory::class,
            \Jield\AssetManager\Resolver\ConcatResolver::class           => \Jield\AssetManager\Service\ConcatResolverServiceFactory::class,
            \Jield\AssetManager\Resolver\AliasPathStackResolver::class   => \Jield\AssetManager\Service\AliasPathStackResolverServiceFactory::class,
            WarmupCommand::class                                         => \Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory::class,
        ],
        'invokables' => [
            \Jield\AssetManager\Service\MimeResolver::class => \Jield\AssetManager\Service\MimeResolver::class,
        ],
        'aliases'    => [
            //Alias left here for BC
            'mime_resolver'      => \Jield\AssetManager\Service\MimeResolver::class,
            'aggregate_resolver' => \Jield\AssetManager\Resolver\AggregateResolver::class
        ],
    ],
    \Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory::class => [
        WarmupCommand::class => [
            \Jield\AssetManager\Service\AssetManager::class,
            'Config'
        ],
    ],
    'asset_manager'                                                      => [
        'clear_output_buffer' => true,
        'resolvers'           => [
            \Jield\AssetManager\Resolver\MapResolver::class              => 3000,
            \Jield\AssetManager\Resolver\ConcatResolver::class           => 2500,
            \Jield\AssetManager\Resolver\CollectionResolver::class       => 2000,
            \Jield\AssetManager\Resolver\PrioritizedPathsResolver::class => 1500,
            \Jield\AssetManager\Resolver\AliasPathStackResolver::class   => 1000,
            \Jield\AssetManager\Resolver\PathStackResolver::class        => 500,
        ],
        'view_helper'         => [
            'append_timestamp' => true,
            'query_string'     => '_',
            'cache'            => null,
        ],
    ],
    'view_helpers'                                                       => [
        'factories' => [
            \Jield\AssetManager\View\Helper\Asset::class => \Jield\AssetManager\Service\AssetViewHelperFactory::class
        ],
        'aliases'   => [
            'asset' => \Jield\AssetManager\View\Helper\Asset::class
        ]
    ],
    'laminas-cli'                                                        => [
        'commands' => [
            'assetmanager:warmup' => WarmupCommand::class,
        ],
    ],
];
