<?php

declare(strict_types=1);

namespace Jield\ErrorHandler;

use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\ModuleManager\Feature\DependencyIndicatorInterface;
use Override;

final class Module implements ConfigProviderInterface, DependencyIndicatorInterface
{
    #[Override]
    public function getConfig(): array
    {
        /** @var array<array-key, mixed> $config */
        $config = include __DIR__ . '/../../resources/error-handler/config/module.config.php';
        return \Webmozart\Assert\Assert::isArray($config);
    }

    #[Override]
    public function getModuleDependencies(): array
    {
        return [
            'DoctrineModule',
            'DoctrineORMModule',
        ];
    }
}
