<?php

namespace Jield\ErrorHandler;

use Jield\ErrorHandler\Command\BaseLoggingCommandInitializer;
use Jield\ErrorHandler\Handler\Logging;
use Jield\ErrorHandler\Handler\LoggingFactory;
use Jield\ErrorHandler\Listener\Mvc;
use Jield\ErrorHandler\Listener\MvcFactory;


return [
    'service_manager' => [
        'abstract_factories' => [

        ],
        'aliases' => ['Jield\\ErrorHandlerLogger' => \Psr\Log\LoggerInterface::class],
        'factories'          => [
            Mvc::class     => MvcFactory::class,
            Logging::class => LoggingFactory::class,
        ],
        'initializers'       => [
            BaseLoggingCommandInitializer::class,
        ],
    ],
    'listeners'       => [
        Mvc::class,
    ],
    'view_manager'    => [
        'template_map' => [
            'error-hero-module/error-default' => __DIR__ . '/../view/error-hero-module/error-default.phtml',
        ],
    ],
];
