<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

interface ConfigMergerInterface extends \Laminas\EventManager\ListenerAggregateInterface
{
    public function getMergedConfig(bool $returnConfigAsObject = true): mixed;

    public function setMergedConfig(array $config): ConfigMergerInterface;
}
