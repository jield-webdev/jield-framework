<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\MvcAuth\Authorization\DefaultResourceResolverListener;
use Laminas\Http\Request;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

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
        /** @mago-var mixed $config */
        $config = $container->has('config') ? $container->get('config') : [];

        return new DefaultResourceResolverListener(restControllers: $this->getRestServicesFromConfig(config: Assert::isArray(
            $config,
        )));
    }

    /**
     * Generate the list of REST services for the listener
     *
     * Looks for api-tools-rest configuration, and creates a list of controller
     * service / identifier name pairs to pass to the listener.
     */
    protected function getRestServicesFromConfig(array $config): array
    {
        $restServices = [];
        if (($config['api-tools-rest'] ?? null) === null) {
            return $restServices;
        }

        /**
         * @mago-var mixed $controllerService
         * @mago-var mixed $restConfig
         */
        foreach (Assert::isIterable($config['api-tools-rest']) as $controllerService => $restConfig) {
            if (($restConfig['route_identifier_name'] ?? null) === null) {
                continue;
            }

            $restServices[\Jield\Stdlib\Value::key($controllerService)] = $restConfig['route_identifier_name'];
        }

        return $restServices;
    }
}
