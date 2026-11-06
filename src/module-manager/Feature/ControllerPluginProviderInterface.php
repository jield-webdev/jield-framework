<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface ControllerPluginProviderInterface
{
    public function getControllerPluginConfig(): array;
}
