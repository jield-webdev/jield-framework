<?php

declare(strict_types=1);

namespace Jield\Export;

use Jield\ModuleManager\Feature\ConfigProviderInterface;

final class Module implements ConfigProviderInterface
{
    public function getConfig(): array
    {
        $configProvider = new ConfigProvider();

        return $configProvider();
    }
}
