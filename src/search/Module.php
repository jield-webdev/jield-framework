<?php

declare(strict_types=1);

namespace Jield\Search;

use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\ModuleManager\Feature\DependencyIndicatorInterface;

final class Module implements ConfigProviderInterface, DependencyIndicatorInterface
{
    public function getConfig(): array
    {
        $configProvider = new ConfigProvider();

        return $configProvider();
    }

    public function getModuleDependencies(): array
    {
        return [
            'Netglue\PsrContainer\Messenger',
        ];
    }
}
