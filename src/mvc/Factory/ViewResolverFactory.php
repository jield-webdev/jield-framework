<?php

namespace Jield\Mvc\Factory;

use Jield\Mvc\Service\ResolverInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Helper\ViewModel as ViewModelHelper;
use Laminas\View\HelperPluginManager;
use Laminas\View\Resolver as ViewResolver;
use Psr\Container\ContainerInterface;

class ViewResolverFactory implements FactoryInterface
{
    /**
     * Create the aggregate view resolver
     *
     * Creates a Laminas\View\Resolver\AggregateResolver and attaches the template
     * map resolver and path stack resolver
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return ViewResolver\AggregateResolver
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): ViewResolver\AggregateResolver
    {
        $resolver = new ViewResolver\AggregateResolver();

        /** @var ResolverInterface $mapResolver */
        $mapResolver = $container->get('ViewTemplateMapResolver');
        /** @var ResolverInterface $pathResolver */
        $pathResolver = $container->get('ViewTemplatePathStack');
        /** @var ResolverInterface $prefixPathStackResolver */
        $prefixPathStackResolver = $container->get('ViewPrefixPathStackResolver');
        $pluginManager           = $container->get(HelperPluginManager::class);
        $viewModelHelper         = $pluginManager->get(ViewModelHelper::class);

        $resolver
            ->attach($mapResolver)
            ->attach($pathResolver)
            ->attach($prefixPathStackResolver)
            ->attach(new ViewResolver\RelativeFallbackResolver($mapResolver, $viewModelHelper))
            ->attach(new ViewResolver\RelativeFallbackResolver($pathResolver, $viewModelHelper))
            ->attach(new ViewResolver\RelativeFallbackResolver($prefixPathStackResolver, $viewModelHelper));

        return $resolver;
    }
}
