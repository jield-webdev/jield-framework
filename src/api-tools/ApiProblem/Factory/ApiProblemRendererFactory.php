<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Jield\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

class ApiProblemRendererFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ApiProblemRenderer
    {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        $displayExceptions =
            ($config['view_manager']['display_exceptions'] ?? null) !== null
            && Value::truthy($config['view_manager']['display_exceptions']);

        $renderer = new ApiProblemRenderer();
        $renderer->setDisplayExceptions(flag: $displayExceptions);

        return $renderer;
    }
}
