<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Psr\Container\ContainerInterface;
use Jield\ApiTools\ApiProblem\Listener\RenderErrorListener;

class RenderErrorListenerFactory
{
    public function __invoke(ContainerInterface $container): RenderErrorListener
    {
        $config            = $container->get('config');
        $displayExceptions = false;

        if (
            isset($config['view_manager'])
            && isset($config['view_manager']['display_exceptions'])
        ) {
            $displayExceptions = (bool) $config['view_manager']['display_exceptions'];
        }

        $listener = new RenderErrorListener();
        $listener->setDisplayExceptions(flag: $displayExceptions);

        return $listener;
    }
}
