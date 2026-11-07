<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
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
            ? Service::get($container, ModuleOptions::class)
            : new ModuleOptions(Value::stringKeyedArray($options));

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
