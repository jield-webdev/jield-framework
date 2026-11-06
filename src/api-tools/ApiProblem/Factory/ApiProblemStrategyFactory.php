<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Jield\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Jield\ApiTools\ApiProblem\View\ApiProblemStrategy;
use Psr\Container\ContainerInterface;

class ApiProblemStrategyFactory
{
    public function __invoke(ContainerInterface $container): ApiProblemStrategy
    {
        return new ApiProblemStrategy(renderer: \Jield\Stdlib\Service::get($container, ApiProblemRenderer::class));
    }
}
