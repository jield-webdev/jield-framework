<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface TranslatorPluginProviderInterface
{
    public function getTranslatorPluginConfig(): array;
}
