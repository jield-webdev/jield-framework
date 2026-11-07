<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Laminas\EventManager\ListenerAggregateInterface;

interface ConfigMergerInterface extends ListenerAggregateInterface
{
    public function getMergedConfig(bool $returnConfigAsObject = true): mixed;

    public function setMergedConfig(array $config): ConfigMergerInterface;
}
