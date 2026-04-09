<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener;

interface ConfigMergerInterface
{
    /**
     * @param bool $returnConfigAsObject
     * @return mixed
     */
    public function getMergedConfig(bool $returnConfigAsObject = true): mixed;

    /**
     * @return ConfigMergerInterface
     */
    public function setMergedConfig(array $config): ConfigMergerInterface;
}
