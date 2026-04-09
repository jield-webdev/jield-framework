<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface SerializerProviderInterface
{
    /** @return array */
    public function getSerializerConfig(): array;
}
