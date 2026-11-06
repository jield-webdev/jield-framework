<?php

declare(strict_types=1);

namespace Jield\Recaptcha;

use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Override;

final class Module implements ConfigProviderInterface
{
    #[Override]
    public function getConfig(): array
    {
        /** @var array<array-key, mixed> $config */
        $config = include __DIR__ . '/../../resources/recaptcha/config/module.config.php';
        return $config;
    }
}
