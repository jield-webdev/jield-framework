<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Listener;

use Jield\ErrorHandler\Handler\Logging;
use Jield\Stdlib\Service;
use Psr\Container\ContainerInterface;

final class MvcFactory
{
    public function __invoke(ContainerInterface $container): Mvc
    {
        /** @var array $config */
        $config = $container->get('config');

        $logging = Service::get($container, Logging::class);

        $viewRenderer = Service::get($container, \Jield\Mvc\View\Renderer\PhpRenderer::class, 'ViewRenderer');

        return new Mvc(
            errorHeroModuleConfig: \Webmozart\Assert\Assert::isArray($config['error-hero-module']),
            logging: $logging,
            phpRenderer: $viewRenderer,
        );
    }
}
