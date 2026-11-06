<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\DispatchListener;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class DispatchListenerFactory implements FactoryInterface
{
    /**
     * Create the default dispatch listener.
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return DispatchListener
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): DispatchListener {
        return new DispatchListener(\Jield\Stdlib\Service::get($container, \Jield\Mvc\Controller\ControllerManager::class, 'ControllerManager'));
    }
}
