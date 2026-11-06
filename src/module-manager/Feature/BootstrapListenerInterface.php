<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

use Laminas\EventManager\EventInterface;

interface BootstrapListenerInterface
{
    public function onBootstrap(EventInterface $e): void;
}
