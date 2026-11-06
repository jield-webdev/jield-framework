<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

use Jield\ModuleManager\ModuleManagerInterface;

interface InitProviderInterface
{
    public function init(ModuleManagerInterface $manager): void;
}
