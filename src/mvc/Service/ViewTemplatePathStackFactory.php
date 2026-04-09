<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Laminas\ServiceManager\Factory\FactoryInterface;
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
     * @param string $requestedName
     * @param null|array $options
     * @return ViewResolver\TemplatePathStack
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): ViewResolver\TemplatePathStack
    {
        $config = $container->get('config');

        $templatePathStack = new ViewResolver\TemplatePathStack();

        if (is_array(value: $config) && isset($config['view_manager'])) {
            $config = $config['view_manager'];
            if (is_array(value: $config)) {
                if (isset($config['template_path_stack'])) {
                    $templatePathStack->addPaths(paths: $config['template_path_stack']);
                }
                if (isset($config['default_template_suffix'])) {
                    $templatePathStack->setDefaultSuffix($config['default_template_suffix']);
                }
            }
        }

        return $templatePathStack;
    }
}
