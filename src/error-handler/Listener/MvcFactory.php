<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Listener;

use Jield\ErrorHandler\Handler\Logging;
use Jield\Mvc\View\Renderer\PhpRenderer;
use Psr\Container\ContainerInterface;

final class MvcFactory
{
    public function __invoke(ContainerInterface $container): Mvc
    {
        /** @var array $config */
        $config = $container->get('config');
        
        $logging = \Jield\Stdlib\Service::get($container, Logging::class);
        
        $viewRenderer = \Jield\Stdlib\Service::get($container, \Jield\Mvc\View\Renderer\PhpRenderer::class, 'ViewRenderer');

        return new Mvc(
            errorHeroModuleConfig: \Jield\Stdlib\Value::array($config['error-hero-module']),
            logging: $logging,
            phpRenderer: $viewRenderer,
        );
    }
}
