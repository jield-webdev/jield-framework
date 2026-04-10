<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Twig\Extension;
use Jield\Twig\View\TwigRenderer;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ExtensionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): Extension
    {
        return new Extension($container->get(TwigRenderer::class));
    }
}
