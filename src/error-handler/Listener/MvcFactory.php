<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Listener;

use Jield\ErrorHandler\Handler\Logging;
use Jield\Mvc\View\Renderer\PhpRenderer;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

final class MvcFactory
{
    public function __invoke(ContainerInterface $container): Mvc
    {
        /** @var array $config */
        $config = $container->get('config');

        $logging = Service::get($container, Logging::class);

        $viewRenderer = Service::get(
            $container,
            \Jield\Mvc\View\Renderer\PhpRenderer::class,
            'ViewRenderer',
        );

        return new Mvc(
            errorHeroModuleConfig: Value::array($config['error-hero-module']),
            logging: $logging,
            phpRenderer: $viewRenderer,
        );
    }
}
