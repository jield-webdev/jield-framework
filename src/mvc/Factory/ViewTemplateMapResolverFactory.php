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
     *
     * @param string $requestedName
     * @param null|array $options
     * @return ViewResolver\TemplateMapResolver
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ViewResolver\TemplateMapResolver {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        $map = [];
        if (is_array($config) && isset($config['view_manager'])) {
            /** @mago-var mixed $config */
            $config = $config['view_manager'];
            if (is_array($config) && isset($config['template_map'])) {
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
