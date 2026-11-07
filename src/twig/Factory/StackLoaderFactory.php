<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Stdlib\Service;
use Jield\Twig\ModuleOptions;
use Jield\Twig\StackLoader;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class StackLoaderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): StackLoader
    {
        $moduleOptions = $options === null
            ? Service::get($container, ModuleOptions::class)
            : new ModuleOptions($options);

        $zfTemplateStack = Service::get(
            $container,
            \Laminas\View\Resolver\TemplatePathStack::class,
            'ViewTemplatePathStack',
        );

        $templateStack = new StackLoader($zfTemplateStack->getPaths()->toArray());
        $templateStack->setDefaultSuffix($moduleOptions->getSuffix());

        return $templateStack;
    }
}
