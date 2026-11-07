<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Helper;

use InvalidArgumentException;
use Jield\Mvc\View\Renderer\PhpRenderer;
use Laminas\View\Helper\HelperInterface;
use Laminas\View\Renderer\RendererInterface;
use LogicException;

abstract class AbstractHelper implements HelperInterface
{
    protected ?PhpRenderer $view = null;

    public function setView(RendererInterface $view): static
    {
        if (!$view instanceof PhpRenderer) {
            throw new InvalidArgumentException('MVC view helpers require the MVC PHP renderer.');
        }
        $this->view = $view;
        return $this;
    }

    public function getView(): PhpRenderer
    {
        return $this->view ?? throw new LogicException('A renderer must be set before using this view helper.');
    }
}
