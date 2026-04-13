<?php

declare(strict_types=1);

namespace Jield\Log;

use Jield\Log\Factory\LoggerAbstractFactory;
use Jield\Log\Factory\LoggerFactory;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class ConfigProvider
{
    /** @return array<string, array> */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            'monolog'       => $this->getMonologConfig(),
        ];
    }

    /** @return array<string, array> */
    public function getDependencyConfig(): array
    {
        return [
            'aliases'            => [
                LoggerInterface::class => Logger::class,
                'logger'               => Logger::class,
            ],
            'factories'          => [
                Logger::class => LoggerFactory::class,
            ],
            'abstract_factories' => [
                LoggerAbstractFactory::class,
            ],
        ];
    }

    /** @return array<string, array> */
    public function getMonologConfig(): array
    {
        return [
            'default_logger' => 'app',
            'loggers'        => [
                'app' => [
                    'name'     => 'app',
                    'handlers' => ['default'],
                ],
                'db'  => [
                    'name'     => 'db',
                    'handlers' => ['doctrine'],
                ],
            ],
            'handlers'       => [
                'default'  => [
                    'type'    => 'stream',
                    'options' => [
                        'stream' => 'data/log/app.log',
                        'level'  => 'debug',
                    ],
                ],
                'doctrine' => [
                    'type'    => 'doctrine_dbal',
                    'options' => [
                        'connection' => 'doctrine.connection.orm_default',
                        'table'      => 'application_log',
                        'level'      => 'error',
                    ],
                ],
            ],
        ];
    }
}
