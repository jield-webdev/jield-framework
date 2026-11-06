<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface FilterProviderInterface
{
    public function getFilterConfig(): array;
}
