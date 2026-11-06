<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin;

use Laminas\Stdlib\DispatchableInterface as Dispatchable;

abstract class AbstractPlugin implements PluginInterface
{
    /** @var null|Dispatchable */
    protected ?Dispatchable $controller = null;

    /**
     * Set the current controller instance
     *
     * @return void
     */
    public function setController(Dispatchable $controller): void
    {
        $this->controller = $controller;
    }

    /**
     * Get the current controller instance
     *
     * @return null|Dispatchable
     */
    public function getController(): ?Dispatchable
    {
        return $this->controller;
    }
}
