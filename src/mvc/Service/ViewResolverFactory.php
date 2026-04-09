<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Helper\ViewModel;
use Laminas\View\Resolver as ViewResolver;
use Laminas\View\Resolver\ResolverInterface;
use Psr\Container\ContainerInterface;

class ViewResolverFactory implements FactoryInterface
{
    /**
     * Create the aggregate view resolver
     *
     * Creates a Laminas\View\Resolver\AggregateResolver and attaches the template
     * map resolver and path stack resolver
     *
     * @param string $requestedName
     * @param null|array $options
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

        $viewManager = $container->get(ViewModel::class);

        $resolver
            ->attach(resolver: $mapResolver)
            ->attach(resolver: $pathResolver)
            ->attach(resolver: $prefixPathStackResolver)
            ->attach(resolver: new ViewResolver\RelativeFallbackResolver(resolver: $mapResolver, viewModelHelper: $viewManager))
            ->attach(resolver: new ViewResolver\RelativeFallbackResolver(resolver: $pathResolver, viewModelHelper: $viewManager))
            ->attach(resolver: new ViewResolver\RelativeFallbackResolver(resolver: $prefixPathStackResolver, viewModelHelper: $viewManager));

        return $resolver;
    }
}
