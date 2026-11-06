<?php

declare(strict_types=1);

namespace Jield\Twig\View;

use Laminas\View\Resolver\ResolverInterface;
use Twig\Environment;

class TwigResolver implements ResolverInterface
{
    public function __construct(
        protected Environment $environment,
    ) {}

    public function resolve(string $name): string|false
    {
        if ($this->environment->getLoader()->exists($name)) {
            return $name;
        }

        return false;
    }
}
