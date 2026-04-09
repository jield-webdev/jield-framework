<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface AutoloaderProviderInterface
{
    /**
     * Return an array for passing to Jield\Loader\AutoloaderFactory.
     *
     * @return array
     */
    public function getAutoloaderConfig(): array;
}
