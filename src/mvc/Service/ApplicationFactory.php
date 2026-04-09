<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Jield\Mvc\Application;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApplicationFactory implements FactoryInterface
{
    /**
     * Create the Application service
     *
     * Creates a Jield\Mvc\Application service, passing it the configuration
     * service and the service manager instance.
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return Application
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        return new Application(
            serviceManager: $container,
            events: $container->get('EventManager'),
            request: $container->get('Request'),
            response: $container->get('Response')
        );
    }
}
