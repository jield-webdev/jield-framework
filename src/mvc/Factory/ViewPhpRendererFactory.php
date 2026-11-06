<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Jield\Mvc\View\Renderer\PhpRenderer;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\HelperPluginManagerInterface;
use Psr\Container\ContainerInterface;

class ViewPhpRendererFactory implements FactoryInterface
{
    /**
     * @param  string $requestedName
     * @param  null|array $options
     * @return PhpRenderer
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): PhpRenderer
    {
        return new PhpRenderer(
            \Jield\Stdlib\Service::get($container, \Laminas\View\Renderer\PhpRenderer::class),
            \Jield\Stdlib\Service::get($container, HelperPluginManagerInterface::class),
        );
    }
}
