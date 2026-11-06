<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use GuzzleHttp\Client;
use Jield\ApiTools\OAuth2\EntraJwksProvider;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

final class EntraJwksProviderFactory
{
    public function __invoke(ContainerInterface $container): EntraJwksProvider
    {
        /** @var mixed $config */
        $config = $container->get('config')['api-tools-oauth2']['entra'] ?? [];

        return new EntraJwksProvider(
            new Client(),
            new FilesystemAdapter('entra_jwks', 3600, \Jield\Stdlib\Value::nullableString($config['cache_directory'] ?? null)),
        );
    }
}
