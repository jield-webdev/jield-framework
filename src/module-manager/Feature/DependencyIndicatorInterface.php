<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface DependencyIndicatorInterface
{
    public function getModuleDependencies(): array;
}
