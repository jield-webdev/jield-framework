<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface InputFilterProviderInterface
{
    public function getInputFilterConfig(): array;
}
