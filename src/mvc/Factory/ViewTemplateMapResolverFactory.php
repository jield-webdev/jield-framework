<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Resolver as ViewResolver;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function is_array;

class ViewTemplateMapResolverFactory implements FactoryInterface
{
    /**
     * Create the template map view resolver
     *
     * Creates a Laminas\View\Resolver\AggregateResolver and populates it with the
     * ['view_manager']['template_map']
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ViewResolver\TemplateMapResolver {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        $map = [];
        if (is_array($config) && ($config['view_manager'] ?? null) !== null) {
            /** @mago-var mixed $config */
            $config = $config['view_manager'];
            if (is_array($config) && ($config['template_map'] ?? null) !== null) {
                $map = Assert::isIterable($config['template_map']);
            }
        }
        $templates = [];
        /**
         * @mago-var mixed $name
         * @mago-var mixed $path
         */
        foreach ($map as $name => $path) {
            $templates[Assert::stringNotEmpty($name)] = Assert::stringNotEmpty($path);
        }
        return new ViewResolver\TemplateMapResolver($templates);
    }
}
