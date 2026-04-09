<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Psr\Container\ContainerInterface;
use Jield\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Jield\ApiTools\ApiProblem\View\ApiProblemStrategy;

class ApiProblemStrategyFactory
{
    public function __invoke(ContainerInterface $container): ApiProblemStrategy
    {
        return new ApiProblemStrategy(renderer: $container->get(ApiProblemRenderer::class));
    }
}
