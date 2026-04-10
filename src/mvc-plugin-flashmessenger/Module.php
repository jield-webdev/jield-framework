<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\FlashMessenger;

use Jield\Mvc\Plugin\FlashMessenger\Factory\FlashMessengerFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;

class Module
{
    public function getConfig(): array
    {
        return [
            'controller_plugins' => [
                'aliases'   => [
                    'flashmessenger' => FlashMessenger::class,
                    'flashMessenger' => FlashMessenger::class,
                ],
                'factories' => [
                    FlashMessenger::class => InvokableFactory::class,
                ],
            ],
            'view_helpers'       => [
                'aliases'   => [
                    'flashmessenger' => View\Helper\FlashMessenger::class,
                    'flashMessenger' => View\Helper\FlashMessenger::class,
                    'FlashMessenger' => View\Helper\FlashMessenger::class,
                ],
                'factories' => [
                    View\Helper\FlashMessenger::class => FlashMessengerFactory::class,
                ],
            ],
        ];
    }
}
