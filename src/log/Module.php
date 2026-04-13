<?php

declare(strict_types=1);

namespace Jield\Log;

use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Override;

final class Module implements ConfigProviderInterface
{
    #[Override]
    public function getConfig(): array
    {
        return new ConfigProvider()();
    }
}
