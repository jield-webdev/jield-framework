<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface ConfigProviderInterface
{
    public function getConfig(): array;
}
