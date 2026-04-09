<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Jield\Mvc\DispatchListener;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DispatchListenerFactory implements FactoryInterface
{
    /**
     * Create the default dispatch listener.
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return DispatchListener
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        return new DispatchListener(controllerManager: $container->get('ControllerManager'));
    }
}
