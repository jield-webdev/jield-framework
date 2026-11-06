<?php

declare(strict_types=1);

namespace Jield\Mvc;

use Laminas\Router\Http\TreeRouteStack;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'service_manager' => [
                'factories' => [TreeRouteStack::class => Factory\HttpRouterFactory::class],
            ],
        ];
    }
}
