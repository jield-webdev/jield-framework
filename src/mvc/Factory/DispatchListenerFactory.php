<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\Controller\ControllerManager;
use Jield\Mvc\DispatchListener;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class DispatchListenerFactory implements FactoryInterface
{
    /**
     * Create the default dispatch listener.
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): DispatchListener {
        return new DispatchListener(Service::get($container, ControllerManager::class, 'ControllerManager'));
    }
}
