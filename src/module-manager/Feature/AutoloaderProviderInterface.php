<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface AutoloaderProviderInterface
{
    public function getAutoloaderConfig(): array;
}
