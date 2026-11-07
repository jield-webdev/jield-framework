<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin;

use Laminas\Stdlib\DispatchableInterface as Dispatchable;

abstract class AbstractPlugin implements PluginInterface
{
    protected ?Dispatchable $controller = null;

    /**
     * Set the current controller instance
     */
    public function setController(Dispatchable $controller): void
    {
        $this->controller = $controller;
    }

    /**
     * Get the current controller instance
     */
    public function getController(): ?Dispatchable
    {
        return $this->controller;
    }
}
