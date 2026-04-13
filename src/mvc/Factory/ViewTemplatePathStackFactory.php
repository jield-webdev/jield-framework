<?php

namespace Jield\Mvc\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Factory\Configuration;
use Laminas\View\Resolver as ViewResolver;
use Psr\Container\ContainerInterface;
use function is_array;

class ViewTemplatePathStackFactory implements FactoryInterface
{
    /**
     * Create the template path stack view resolver
     *
     * Creates a Laminas\View\Resolver\TemplatePathStack and populates it with the
     * ['view_manager']['template_path_stack'] and sets the default suffix with the
     * ['view_manager']['default_template_suffix']
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return ViewResolver\TemplatePathStack
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): ViewResolver\TemplatePathStack
    {
        $config = Configuration::get($container);
        $paths  = $config['view_manager']['template_path_stack'] ?? [];

        return new ViewResolver\TemplatePathStack([
            'default_suffix' => Configuration::defaultTemplateSuffix($container),
            'script_paths'   => is_array($paths) ? $paths : [],
        ]);
    }
}
