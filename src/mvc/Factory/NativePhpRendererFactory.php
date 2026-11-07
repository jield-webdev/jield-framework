<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Factory\Configuration;
use Laminas\View\HelperPluginManagerInterface;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\ResolverInterface;
use Psr\Container\ContainerInterface;

class NativePhpRendererFactory implements FactoryInterface
{
    /**
     * @param  string $requestedName
     * @param  null|array $options
     * @return PhpRenderer
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): PhpRenderer
    {
        return new PhpRenderer(
            Service::get($container, HelperPluginManagerInterface::class),
            Service::get($container, ResolverInterface::class),
            Configuration::strictVariables($container),
        );
    }
}
