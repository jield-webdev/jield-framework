<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface DependencyIndicatorInterface
{
    /**
     * Expected to return an array of modules on which the current one depends on
     *
     * @return array
     */
    public function getModuleDependencies(): array;
}
