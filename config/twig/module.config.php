<?php

use Jield\Twig\Extension;
use Jield\Twig\MapLoader;
use Jield\Twig\StackLoader;
use Jield\Twig\View;
use Laminas\Navigation\View\HelperConfig;
use Twig\Environment;
use Twig\Loader\ChainLoader;

return [
    'jield_twig' => [
        /**
         * Service manager alias of the loader to use with Jield Twig.
         */
        'environment_loader' => ChainLoader::class,

        /**
         * Optional class name override for instantiating the Twig Environment in the factory.
         */
        'environment_class' => Environment::class,

        /**
         * Options that are passed directly to the Twig_Environment.
         */
        'environment_options' => [],

        /**
         * Service manager alias of any additional loaders to register with the chain.
         */
        'loader_chain' => [
            MapLoader::class,
            StackLoader::class
        ],

        /**
         * Service manager alias or fully qualified domain name of extensions.
         */
        'extensions' => [
            Extension::class
        ],

        /**
         * The suffix of Twig files. Technically, Twig can load *any* type of file
         * but the templates in ZF are suffix agnostic so we must specify the extension
         * that's expected here.
         */
        'suffix' => 'twig',

        /**
         * When enabled, the view helpers will get pulled using a fallback renderer. This will
         * slightly degrade performance but must be used if you plan on using any view helpers.
         */
        'enable_fallback_functions' => true,

        /**
         * If set to true disables ZF's notion of parent/child layouts in favor of
         * Twig's inheritance model.
         */
        'disable_zf_model' => true,

        /**
         * Jield Twig uses its own HelperPluginManager to avoid renderer conflicts with the PhpRenderer. You must register
         * any view helpers in this array that require access to the renderer. The defaults from Laminas (navigation,
         * partial, etc.) are done for you.
         */
        'helper_manager' => [
            'configs' => [
                HelperConfig::class
            ]
        ]
    ],

    /**
     * Load services.
     */
    'service_manager' => include __DIR__ . '/service.config.php',

    /**
     * Register the view strategy with the view manager. This is required!
     */
    'view_manager' => [
        'strategies' => [View\TwigStrategy::class]
    ]
];
