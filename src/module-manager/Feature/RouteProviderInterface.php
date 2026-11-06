<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface RouteProviderInterface
{
    public function getRouteConfig(): array;
}
