<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface ViewHelperProviderInterface
{
    public function getViewHelperConfig(): array;
}
