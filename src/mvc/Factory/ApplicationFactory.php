<?php

declare(strict_types=1);

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
            \Jield\Stdlib\Service::get($container, \Laminas\EventManager\EventManagerInterface::class, 'EventManager'),
            \Jield\Stdlib\Service::get($container, \Laminas\Stdlib\RequestInterface::class, 'Request'),
            \Jield\Stdlib\Service::get($container, \Laminas\Stdlib\ResponseInterface::class, 'Response'),
        );
    }
}
