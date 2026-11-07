<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Jield\ApiTools\ApiProblem\Listener\RenderErrorListener;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

class RenderErrorListenerFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): RenderErrorListener
    {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        $displayExceptions = false;

        if (($config['view_manager']['display_exceptions'] ?? null) !== null) {
            $displayExceptions = Value::truthy($config['view_manager']['display_exceptions']);
        }

        $listener = new RenderErrorListener();
        $listener->setDisplayExceptions(flag: $displayExceptions);

        return $listener;
    }
}
