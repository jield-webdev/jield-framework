<?php

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\Application;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

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
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): Application
    {
        return new Application(
            $container,
            $container->get('EventManager'),
            $container->get('Request'),
            $container->get('Response')
        );
    }
}
