<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface FormElementProviderInterface
{
    public function getFormElementConfig(): array;
}
