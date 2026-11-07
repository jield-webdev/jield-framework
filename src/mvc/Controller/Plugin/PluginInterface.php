<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin;

use Laminas\Stdlib\DispatchableInterface as Dispatchable;

interface PluginInterface
{
    /**
     * Set the current controller instance
     */
    public function setController(Dispatchable $controller): void;

    /**
     * Get the current controller instance
     */
    public function getController(): ?Dispatchable;
}
