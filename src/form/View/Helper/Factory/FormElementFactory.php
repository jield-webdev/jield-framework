<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper\Factory;

use Jield\Form\View\Helper\FormElement;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\HelperPluginManagerInterface;
use Psr\Container\ContainerInterface;

final class FormElementFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): FormElement
    {
        return new FormElement($container->get(HelperPluginManagerInterface::class));
    }
}
