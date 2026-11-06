<?php

declare(strict_types=1);

namespace Jield\Export;

use Jield\Export\Command\ListEntities;
use Jield\Export\Command\RenderDocumentation;
use Jield\Export\Command\SendEntity;
use Jield\Export\Factory\ConsoleServiceFactory;
use Jield\Export\Options\ModuleOptions;
use Jield\Export\Service\ConsoleService;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            ConfigAbstractFactory::class => $this->getConfigAbstractFactory(),
            'service_manager' => $this->getServiceMangerConfig(),
            'laminas-cli' => $this->getCommandConfig(),
        ];
    }

    public function getCommandConfig(): array
    {
        return [
            'commands' => [
                'export:send' => SendEntity::class,
                'export:list' => ListEntities::class,
                'export:documentation' => RenderDocumentation::class,
            ],
        ];
    }

    public function getServiceMangerConfig(): array
    {
        return [
            'factories' => [
                ConsoleService::class => ConsoleServiceFactory::class,
                ModuleOptions::class => Factory\ModuleOptionsFactory::class,
                SendEntity::class => ConfigAbstractFactory::class,
                ListEntities::class => ConfigAbstractFactory::class,
                RenderDocumentation::class => ConfigAbstractFactory::class,
            ],
        ];
    }

    public function getConfigAbstractFactory(): array
    {
        return [
            SendEntity::class => [
                ConsoleService::class,
            ],
            ListEntities::class => [
                ConsoleService::class,
            ],
            RenderDocumentation::class => [
                ConsoleService::class,
            ],
        ];
    }
}
