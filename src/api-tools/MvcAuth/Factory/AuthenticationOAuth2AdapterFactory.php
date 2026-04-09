<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\MvcAuth\Authentication\OAuth2Adapter;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Container\ContainerInterface;

use function is_array;

final class AuthenticationOAuth2AdapterFactory
{
    /**
     * Intentionally empty and private to prevent instantiation
     */
    private function __construct()
    {
    }

    /**
     * Create and return an OAuth2Adapter instance.
     *
     * @param array|string $type
     * @param array $config
     * @param ContainerInterface $container
     * @return OAuth2Adapter
     */
    public static function factory(array|string $type, array $config, ContainerInterface $container): OAuth2Adapter
    {
        if (! isset($config['storage']) || ! is_array(value: $config['storage'])) {
            throw new ServiceNotCreatedException(message: 'Missing storage details for OAuth2 server');
        }

        return new OAuth2Adapter(
            oauth2Server: OAuth2ServerFactory::factory(config: $config['storage'], container: $container),
            types: $type
        );
    }
}
