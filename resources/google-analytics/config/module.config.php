<?php

use Jield\GoogleAnalytics\Analytics\Tracker;
use Jield\GoogleAnalytics\Service\GoogleAnalyticsFactory;
use Jield\GoogleAnalytics\Service\ScriptFactory;
use Jield\GoogleAnalytics\Service\TrackerFactory;
use Jield\GoogleAnalytics\View\Helper\Script\Gajs;

return [
    'google_analytics' =>
        [
            'enable' => true,
            'id'     => '',
        ],
    'service_manager'  => [
        'aliases'    => [
            'google-analytics'    => Tracker::class,
            'google-analytics-ga' => Gajs::class
        ],
        'invokables' => [
            Gajs::class => Gajs::class
        ],
        'factories'  => [
            Tracker::class       => TrackerFactory::class,
            ScriptFactory::class => ScriptFactory::class
        ]
    ],
    'view_helpers'     => [
        'factories' => [
            'googleAnalytics' => GoogleAnalyticsFactory::class
        ]
    ]
];
