<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Twig\View\TwigRenderer;
use Jield\Twig\View\TwigStrategy;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class TwigStrategyFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): TwigStrategy
    {
        return new TwigStrategy(\Jield\Stdlib\Service::get($container, TwigRenderer::class));
    }
}
