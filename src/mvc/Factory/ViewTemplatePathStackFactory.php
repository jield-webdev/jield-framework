<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Factory\Configuration;
use Laminas\View\Resolver as ViewResolver;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

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
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ViewResolver\TemplatePathStack {
        $config = Configuration::get($container);
        $paths = $config['view_manager']['template_path_stack'] ?? [];

        $paths = is_array($paths) ? array_values(array_map(Assert::stringNotEmpty(...), $paths)) : [];

        return new ViewResolver\TemplatePathStack([
            'default_suffix' => Configuration::defaultTemplateSuffix($container),
            'script_paths' => $paths,
        ]);
    }
}
