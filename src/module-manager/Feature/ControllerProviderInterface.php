<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface ControllerProviderInterface
{
    public function getControllerConfig(): array;
}
