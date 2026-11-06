<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface ServiceProviderInterface
{
    public function getServiceConfig(): array;
}
