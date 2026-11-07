<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\MvcAuth\Authentication\HttpAdapter;
use Jield\Stdlib\Service;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Container\ContainerInterface;

use function is_array;

final class AuthenticationHttpAdapterFactory
{
    /**
     * Intentionally empty and private to prevent instantiation
     */
    private function __construct()
    {
    }

    /**
     * Create an instance of HttpAdapter based on the configuration provided
     * and the registered AuthenticationService.
     *
     * @param string $type The base "type" the adapter will provide
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public static function factory(string $type, array $config, ContainerInterface $container): HttpAdapter
    {
        if (!$container->has('authentication')) {
            throw new ServiceNotCreatedException(
                message: 'Cannot create HTTP authentication adapter; missing AuthenticationService',
            );
        }

        if (($config['options'] ?? null) === null || !is_array(value: $config['options'])) {
            throw new ServiceNotCreatedException(message: 'Cannot create HTTP authentication adapter; missing options');
        }

        return new HttpAdapter(
            httpAuth: HttpAdapterFactory::factory(config: $config['options'], container: $container),
            authenticationService: Service::get($container, AuthenticationServiceInterface::class, 'authentication'),
            providesBase: $type,
        );
    }
}
