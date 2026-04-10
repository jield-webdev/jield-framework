<?php

namespace Jield\Mvc\Controller\Plugin;

use Laminas\Stdlib\DispatchableInterface as Dispatchable;

interface PluginInterface
{
    /**
     * Set the current controller instance
     *
     * @return void
     */
    public function setController(Dispatchable $controller): void;

    /**
     * Get the current controller instance
     *
     * @return null|Dispatchable
     */
    public function getController(): ?Dispatchable;
}
