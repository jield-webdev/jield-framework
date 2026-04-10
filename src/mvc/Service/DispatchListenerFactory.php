<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Interop\Container\ContainerInterface;
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
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DispatchListener
    {
        return new DispatchListener($container->get('ControllerManager'));
    }
}
