<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Resolver\PrefixPathStackResolver;
use Psr\Container\ContainerInterface;

class ViewPrefixPathStackResolverFactory implements FactoryInterface
{
    /**
     * Create the template prefix view resolver
     *
     * Creates a Laminas\View\Resolver\PrefixPathStackResolver and populates it with the
     * ['view_manager']['prefix_template_path_stack']
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): PrefixPathStackResolver
    {
        $config   = $container->get('config');
        $prefixes = $config['view_manager']['prefix_template_path_stack'] ?? [];

        return new PrefixPathStackResolver(prefixes: $prefixes);
    }
}
