<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Jield\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Psr\Container\ContainerInterface;

class ApiProblemRendererFactory
{
    public function __invoke(ContainerInterface $container): ApiProblemRenderer
    {
        /** @var mixed $config */
        $config = $container->get('config');
        $displayExceptions =
            isset($config['view_manager']['display_exceptions']) && \Jield\Stdlib\Value::truthy($config['view_manager']['display_exceptions']);

        $renderer = new ApiProblemRenderer();
        $renderer->setDisplayExceptions(flag: $displayExceptions);

        return $renderer;
    }
}
