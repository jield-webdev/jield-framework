<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

use Laminas\ServiceManager\Config;

interface FormElementProviderInterface
{
    /**
     * Expected to return \Laminas\ServiceManager\Config object or array to
     * seed such an object.
     *
     * @return array|Config
     */
    public function getFormElementConfig(): array|Config;
}
