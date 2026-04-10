<?php

declare(strict_types=1);

namespace Jield\Twig\View;

use Jield\Mvc\Plugin\FlashMessenger;
use Laminas\ServiceManager\Factory\InvokableFactory;

class HelperPluginManager extends \Laminas\View\HelperPluginManager
{
    /**
     * Default aliases
     *
     * @var string[]
     */
    protected array $aliases
        = [
            'flashmessenger' => FlashMessenger\FlashMessenger::class,
        ];

    /**
     * Default factories
     *
     * @var string[]
     */
    protected array $factories
        = [
            FlashMessenger\FlashMessenger::class => InvokableFactory::class,
        ];
}
