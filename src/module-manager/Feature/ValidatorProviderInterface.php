<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

interface ValidatorProviderInterface
{
    public function getValidatorConfig(): array;
}
