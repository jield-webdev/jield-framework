<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Feature;

use Jield\ModuleManager\ModuleManagerInterface;

interface InitProviderInterface
{
    /**
     * Initialize workflow
     *
     * @return void
     */
    public function init(ModuleManagerInterface $manager): void;
}
