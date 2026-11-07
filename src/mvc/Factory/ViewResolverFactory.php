<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Helper\ViewModel as ViewModelHelper;
use Laminas\View\HelperPluginManager;
use Laminas\View\Resolver as ViewResolver;
use Laminas\View\Resolver\PrefixPathStackResolver;
use Laminas\View\Resolver\TemplateMapResolver;
use Laminas\View\Resolver\TemplatePathStack;
use Psr\Container\ContainerInterface;

class ViewResolverFactory implements FactoryInterface
{
    /**
     * Create the aggregate view resolver
     *
     * Creates a Laminas\View\Resolver\AggregateResolver and attaches the template
     * map resolver and path stack resolver
     *
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param null|array $options
     * @return ViewResolver\AggregateResolver
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ViewResolver\AggregateResolver {
        $resolver = new ViewResolver\AggregateResolver();

        $mapResolver = Service::get(
            $container,
            \Laminas\View\Resolver\TemplateMapResolver::class,
            'ViewTemplateMapResolver',
        );

        $pathResolver = Service::get(
            $container,
            \Laminas\View\Resolver\TemplatePathStack::class,
            'ViewTemplatePathStack',
        );
        /** @var PrefixPathStackResolver $prefixPathStackResolver */
        $prefixPathStackResolver = $container->get('ViewPrefixPathStackResolver');
        $pluginManager = Service::get($container, HelperPluginManager::class);
        $viewModelHelper = $pluginManager->get(ViewModelHelper::class);

        $resolver
            ->attach($mapResolver)
            ->attach($pathResolver)
            ->attach($prefixPathStackResolver)
            ->attach(
                new ViewResolver\RelativeFallbackResolver($mapResolver, Value::instance(
                    $viewModelHelper,
                    \Laminas\View\Helper\ViewModel::class,
                )),
            )
            ->attach(
                new ViewResolver\RelativeFallbackResolver($pathResolver, Value::instance(
                    $viewModelHelper,
                    \Laminas\View\Helper\ViewModel::class,
                )),
            )
            ->attach(
                new ViewResolver\RelativeFallbackResolver($prefixPathStackResolver, Value::instance(
                    $viewModelHelper,
                    \Laminas\View\Helper\ViewModel::class,
                )),
            );

        return $resolver;
    }
}
