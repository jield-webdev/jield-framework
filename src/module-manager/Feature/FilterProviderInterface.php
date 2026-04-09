<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

use Laminas\ServiceManager\Config;

interface FilterProviderInterface
{
    /**
     * Expected to return \Laminas\ServiceManager\Config object or array to
     * seed such an object.
     *
     * @return array|Config
     */
    public function getFilterConfig(): array|Config;
}
