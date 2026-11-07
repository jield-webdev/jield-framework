<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\Application;
use Jield\Stdlib\Service;
use Laminas\EventManager\EventManagerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Stdlib\RequestInterface;
use Laminas\Stdlib\ResponseInterface;
use Psr\Container\ContainerInterface;

class ApplicationFactory implements FactoryInterface
{
    /**
     * Create the Application service
     *
     * Creates a Jield\Mvc\Application service, passing it the configuration
     * service and the service manager instance.
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): Application
    {
        return new Application(
            $container,
            Service::get($container, EventManagerInterface::class, 'EventManager'),
            Service::get($container, RequestInterface::class, 'Request'),
            Service::get($container, ResponseInterface::class, 'Response'),
        );
    }
}
