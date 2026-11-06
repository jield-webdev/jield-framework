<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\FlashMessenger;

use Jield\Mvc\Plugin\FlashMessenger\Factory\FlashMessengerFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'controller_plugins' => $this->getControllerPluginConfig(),
            'view_helpers' => $this->getViewHelperConfig(),
        ];
    }

    public function getControllerPluginConfig(): array
    {
        return [
            'aliases' => [
                'flashmessenger' => FlashMessenger::class,
                'flashMessenger' => FlashMessenger::class,
            ],
            'factories' => [
                FlashMessenger::class => InvokableFactory::class,
            ],
        ];
    }

    public function getViewHelperConfig(): array
    {
        return [
            'aliases' => [
                'flashmessenger' => View\Helper\FlashMessenger::class,
                'flashMessenger' => View\Helper\FlashMessenger::class,
                'FlashMessenger' => View\Helper\FlashMessenger::class,
            ],
            'factories' => [
                View\Helper\FlashMessenger::class => FlashMessengerFactory::class,
            ],
        ];
    }
}
