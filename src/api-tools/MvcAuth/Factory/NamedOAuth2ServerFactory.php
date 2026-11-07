<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\OAuth2\Factory\OAuth2ServerInstanceFactory;
use Jield\Stdlib\Value;
use OAuth2\Server;
use Psr\Container\ContainerInterface;

/**
 * Override factory for the Jield\ApiTools\OAuth2\Service\OAuth2Server service.
 *
 * This factory returns a factory that will allow retrieving a named
 * OAuth2\Server instance. It delegates to
 * Jield\ApiTools\OAuth2\Factory\OAuth2ServerInstanceFactory after first marshaling the
 * correct configuration from api-tools-mvc-auth.authentication.adapters.
 */
class NamedOAuth2ServerFactory
{
    public function __invoke(ContainerInterface $container): callable
    {
        $config = $container->get('config');

        /** @var mixed $oauth2Config */
        $oauth2Config = $config['api-tools-oauth2'] ?? [];
        /** @var mixed $mvcAuthConfig */
        $mvcAuthConfig = $config['api-tools-mvc-auth']['authentication']['adapters'] ?? [];

        $servers = new class {
            public ?Server $application = null;
            /** @var array<array-key, Server> */
            public array $api = [];
        };
        return static function ($type = null) use ($oauth2Config, $mvcAuthConfig, $container, $servers) {
            // Empty type == legacy configuration.
            if (empty($type)) {
                if (null !== $servers->application) {
                    return $servers->application;
                }

                $factory = new OAuth2ServerInstanceFactory(
                    config: Value::array($oauth2Config),
                    services: $container,
                );
                return $servers->application = $factory();
            }

            if (isset($servers->api[$type])) {
                return $servers->api[$type];
            }

            foreach (Value::iterable($mvcAuthConfig) as $adapterConfig) {
                if (!isset($adapterConfig['storage']['route'])) {
                    // Not a api-tools-oauth2 config
                    continue;
                }

                if ($type !== $adapterConfig['storage']['route']) {
                    continue;
                }

                // Found!
                return $servers->api[Value::key($type)] = OAuth2ServerFactory::factory(
                    config: $adapterConfig['storage'],
                    container: $container,
                );
            }

            // At this point, a $type was specified, but no matching adapter
            // was found. Attempt to pull a global OAuth2 instance; if none is
            // present, this will raise an exception anyways.
            if (null !== $servers->application) {
                return $servers->application;
            }

            $factory = new OAuth2ServerInstanceFactory(
                config: Value::array($oauth2Config),
                services: $container,
            );
            return $servers->application = $factory();
        };
    }
}
