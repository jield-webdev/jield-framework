<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\Identity;

use Jield\Mvc\Plugin\Identity\Factory\IdentityFactory;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'controller_plugins' => $this->getControllerPluginConfig(),
        ];
    }

    public function getControllerPluginConfig(): array
    {
        return [
            'aliases'   => [
                'identity' => Identity::class,
                'Identity' => Identity::class,
            ],
            'factories' => [
                Identity::class => IdentityFactory::class,
            ],
        ];
    }
}
