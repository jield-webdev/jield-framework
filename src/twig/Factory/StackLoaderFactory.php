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
        if (null === $options) {
            /** @var ModuleOptions $options */
            $options = $container->get(ModuleOptions::class);
        }

        /** @var TemplatePathStack $zfTemplateStack */
        $zfTemplateStack = $container->get('ViewTemplatePathStack');

        $templateStack = new StackLoader($zfTemplateStack->getPaths()->toArray());
        $templateStack->setDefaultSuffix($options->getSuffix());

        return $templateStack;
    }
}
