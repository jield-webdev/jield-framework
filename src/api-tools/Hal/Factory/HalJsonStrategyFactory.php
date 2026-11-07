<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\View\HalJsonRenderer;
use Jield\ApiTools\Hal\View\HalJsonStrategy;
use Jield\Stdlib\Service;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class HalJsonStrategyFactory
{
    public function __invoke(ContainerInterface $container): HalJsonStrategy
    {
        $renderer = Service::get($container, HalJsonRenderer::class);

        return new HalJsonStrategy(renderer: $renderer);
    }
}
