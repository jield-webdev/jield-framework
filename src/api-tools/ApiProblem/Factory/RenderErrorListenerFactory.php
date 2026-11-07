<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Jield\ApiTools\ApiProblem\Listener\RenderErrorListener;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

class RenderErrorListenerFactory
{
    public function __invoke(ContainerInterface $container): RenderErrorListener
    {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        $displayExceptions = false;

        if (isset($config['view_manager']['display_exceptions'])) {
            $displayExceptions = Value::truthy($config['view_manager']['display_exceptions']);
        }

        $listener = new RenderErrorListener();
        $listener->setDisplayExceptions(flag: $displayExceptions);

        return $listener;
    }
}
