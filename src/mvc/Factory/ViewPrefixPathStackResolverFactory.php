<?php

namespace Jield\Mvc\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Factory\Configuration;
use Laminas\View\Resolver\PrefixPathStackResolver;
use Psr\Container\ContainerInterface;

class ViewPrefixPathStackResolverFactory implements FactoryInterface
{
    /**
     * Create the template prefix view resolver
     *
     * Creates a Laminas\View\Resolver\PrefixPathStackResolver and populates it with the
     * ['view_manager']['prefix_template_path_stack']
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return PrefixPathStackResolver
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): PrefixPathStackResolver
    {
        $config   = Configuration::get($container);
        $prefixes = $config['view_manager']['prefix_template_path_stack'] ?? [];

        return new PrefixPathStackResolver(
            $prefixes,
            Configuration::defaultTemplateSuffix($container),
        );
    }
}
