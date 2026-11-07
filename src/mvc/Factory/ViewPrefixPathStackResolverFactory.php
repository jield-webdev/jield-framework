<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Factory\Configuration;
use Laminas\View\Resolver\PrefixPathStackResolver;
use Laminas\View\Resolver\ResolverInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class ViewPrefixPathStackResolverFactory implements FactoryInterface
{
    /**
     * Create the template prefix view resolver
     *
     * Creates a Laminas\View\Resolver\PrefixPathStackResolver and populates it with the
     * ['view_manager']['prefix_template_path_stack']
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): PrefixPathStackResolver {
        $config = Configuration::get($container);
        $prefixes = Assert::isIterable($config['view_manager']['prefix_template_path_stack'] ?? []);

        $paths = [];
        /**
         * @mago-var mixed $prefix
         * @mago-var mixed $path
         */
        foreach ($prefixes as $prefix => $path) {
            $paths[Assert::stringNotEmpty($prefix)] = match (true) {
                $path instanceof ResolverInterface => $path,
                is_array($path) => array_values(array_map(Assert::stringNotEmpty(...), $path)),
                default => Assert::stringNotEmpty($path),
            };
        }
        return new PrefixPathStackResolver($paths, Configuration::defaultTemplateSuffix($container));
    }
}
