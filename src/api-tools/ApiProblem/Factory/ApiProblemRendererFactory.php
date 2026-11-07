<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Jield\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

class ApiProblemRendererFactory
{
    public function __invoke(ContainerInterface $container): ApiProblemRenderer
    {
        $config = $container->get('config');
        $displayExceptions =
            isset($config['view_manager']['display_exceptions'])
            && Value::truthy($config['view_manager']['display_exceptions']);

        $renderer = new ApiProblemRenderer();
        $renderer->setDisplayExceptions(flag: $displayExceptions);

        return $renderer;
    }
}
