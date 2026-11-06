<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\MvcAuth\Authorization\DefaultResourceResolverListener;
use Laminas\Http\Request;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating the DefaultResourceResolverListener from configuration.
 */
class DefaultResourceResolverListenerFactory implements FactoryInterface
{
    /** @var array<Request::METHOD_*, bool> */
    protected array $httpMethods = [
        Request::METHOD_DELETE => true,
        Request::METHOD_GET => true,
        Request::METHOD_PATCH => true,
        Request::METHOD_POST => true,
        Request::METHOD_PUT => true,
    ];

    /**
     * Create and return a DefaultResourceResolverListener instance.
     *
     * @param string             $requestedName
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): DefaultResourceResolverListener {
        /** @var mixed $config */
        $config = $container->has('config') ? $container->get('config') : [];

        return new DefaultResourceResolverListener(restControllers: $this->getRestServicesFromConfig(config: \Jield\Stdlib\Value::array($config)));
    }

    /**
     * Generate the list of REST services for the listener
     *
     * Looks for api-tools-rest configuration, and creates a list of controller
     * service / identifier name pairs to pass to the listener.
     *
     * @param array $config
     * @return array
     */
    protected function getRestServicesFromConfig(array $config): array
    {
        $restServices = [];
        if (!isset($config['api-tools-rest'])) {
            return $restServices;
        }

        /** @var mixed $controllerService
         * @var mixed $restConfig
         */
        foreach (\Jield\Stdlib\Value::iterable($config['api-tools-rest']) as $controllerService => $restConfig) {
            if (!isset($restConfig['route_identifier_name'])) {
                continue;
            }

            $restServices[\Jield\Stdlib\Value::key($controllerService)] = $restConfig['route_identifier_name'];
        }

        return $restServices;
    }
}
