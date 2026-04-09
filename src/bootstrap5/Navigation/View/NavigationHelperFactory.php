<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Navigation\View;

use Jield\Bootstrap5\View\Helper\Navigation;
use Jield\Bootstrap5\View\Helper\Navigation\Menu;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Translator\TranslatorInterface;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\Helper\EscapeHtmlAttr;
use Laminas\View\Helper\HtmlAttributes;
use Psr\Container\ContainerInterface;
use ReflectionProperty;

final class NavigationHelperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): Navigation
    {
        $services = $this->getApplicationServicesFromContainer($container);

        $menu = new Menu(
            escapeHtml: $container->get(EscapeHtml::class),
            escapeHtmlAttr: $container->get(EscapeHtmlAttr::class),
            htmlAttributes: $container->get(HtmlAttributes::class),
            translator: $services->has(TranslatorInterface::class)
                ? $services->get(TranslatorInterface::class)
                : null
        );

        return new Navigation($menu, $services);
    }

    private function getApplicationServicesFromContainer(ContainerInterface $container): ContainerInterface
    {
        try {
            $r = new ReflectionProperty($container, 'creationContext');
            $services = $r->getValue($container);
        } catch (\ReflectionException) {
            return $container;
        }

        return $services instanceof ContainerInterface ? $services : $container;
    }
}
