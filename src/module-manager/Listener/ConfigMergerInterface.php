<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

use Laminas\EventManager\ListenerAggregateInterface;

interface ConfigMergerInterface extends ListenerAggregateInterface
{
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(1)
    public function getMergedConfig(bool $returnConfigAsObject = true): mixed;

    public function setMergedConfig(array $config): ConfigMergerInterface;
}
