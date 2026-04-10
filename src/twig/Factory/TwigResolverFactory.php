<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Twig\View\TwigResolver;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Twig\Environment;

class TwigResolverFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): TwigResolver
    {
        return new TwigResolver($container->get(Environment::class));
    }
}
