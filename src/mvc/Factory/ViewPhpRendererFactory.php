<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Jield\Mvc\View\Renderer\PhpRenderer;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\HelperPluginManagerInterface;
use Psr\Container\ContainerInterface;

class ViewPhpRendererFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): PhpRenderer
    {
        return new PhpRenderer(
            Service::get($container, \Laminas\View\Renderer\PhpRenderer::class),
            Service::get($container, HelperPluginManagerInterface::class),
        );
    }
}
