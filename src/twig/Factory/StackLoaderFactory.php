<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Twig\ModuleOptions;
use Jield\Twig\StackLoader;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Resolver\TemplatePathStack;
use Psr\Container\ContainerInterface;

class StackLoaderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): StackLoader
    {
        $moduleOptions = $options === null
            ? \Jield\Stdlib\Service::get($container, ModuleOptions::class)
            : new ModuleOptions(\Jield\Stdlib\Value::stringKeyedArray($options));

        
        $zfTemplateStack = \Jield\Stdlib\Service::get($container, \Laminas\View\Resolver\TemplatePathStack::class, 'ViewTemplatePathStack');

        $templateStack = new StackLoader($zfTemplateStack->getPaths()->toArray());
        $templateStack->setDefaultSuffix($moduleOptions->getSuffix());

        return $templateStack;
    }
}
