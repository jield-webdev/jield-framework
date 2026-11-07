<?php

declare(strict_types=1);

namespace Jield\Twig;

use Jield\Twig\View\TwigRenderer;
use Twig\Extension\AbstractExtension;

class Extension extends AbstractExtension
{
    public function __construct(
        protected TwigRenderer $renderer,
    ) {
    }

    public function getRenderer(): TwigRenderer
    {
        return $this->renderer;
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName(): string
    {
        return self::class;
    }
}
