<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\MvcAuth\Authentication\DefaultAuthenticationListener;
use Jield\ApiTools\MvcAuth\Authentication\HttpAdapter;
use Jield\ApiTools\MvcAuth\Authentication\OAuth2Adapter;
use Jield\ApiTools\OAuth2\Factory\OAuth2ServerFactory as LaminasOAuth2ServerFactory;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Authentication\Adapter\Http;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OAuth2\Server;
use Override;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Webmozart\Assert\Assert;

use function is_array;
use function is_string;

/**
 * Factory for creating the DefaultAuthenticationListener from configuration.
 */
// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
class DefaultAuthenticationListenerFactory implements FactoryInterface
{
    /**
     * Create and return a DefaultAuthenticationListener.
     *
     * @param string $requestedName
     * @throws RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): DefaultAuthenticationListener {
        $listener = new DefaultAuthenticationListener();

        $httpAdapter = $this->retrieveHttpAdapter(container: $container);
        if ($httpAdapter) {
            $listener->attach(adapter: $httpAdapter);
        }

        $oauth2Server = $this->createOAuth2Server(container: $container);
        if ($oauth2Server) {
            $listener->attach(adapter: $oauth2Server);
        }

        $authenticationTypes = $this->getAuthenticationTypes(container: $container);
        if ($authenticationTypes) {
            $listener->addAuthenticationTypes(types: $authenticationTypes);
        }

        $listener->setAuthMap(map: $this->getAuthenticationMap(container: $container));

        return $listener;
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function retrieveHttpAdapter(ContainerInterface $container): false|HttpAdapter
    {
        // Allow applications to provide their own AuthHttpAdapter service; if none provided,
        // or no HTTP adapter configuration provided to api-tools-mvc-auth, we can stop early.

        $httpAdapter = Service::getOrFalse($container, Http::class);

        if ($httpAdapter === false) {
            return false;
        }

        $httpAdapter = Assert::isInstanceOf($httpAdapter, Http::class);

        // We must abort if no resolver was provided
        if (!$httpAdapter->getBasicResolver() && !$httpAdapter->getDigestResolver()) {
            return false;
        }

        $authService = Service::get($container, AuthenticationServiceInterface::class, 'authentication');

        return new HttpAdapter(httpAuth: $httpAdapter, authenticationService: $authService);
    }

    /**
     * Create an OAuth2 server by introspecting the config service
     *
     * @throws RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function createOAuth2Server(ContainerInterface $container): false|OAuth2Adapter
    {
        if (!$container->has('config')) {
            // If we don't have configuration, we cannot create an OAuth2 server.
            return false;
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');
        if (
            ($config['api-tools-oauth2']['storage'] ?? null) === null
            || !is_string(value: $config['api-tools-oauth2']['storage'])
            || !$container->has($config['api-tools-oauth2']['storage'])
        ) {
            return false;
        }

        if ($container->has('Jield\ApiTools\OAuth2\Service\OAuth2Server')) {
            // If the service locator already has a pre-configured OAuth2 server, use it.
            $factory = Assert::isCallable($container->get('Jield\ApiTools\OAuth2\Service\OAuth2Server'));

            $types = array_values(array_unique([
                'oauth2',
                ...Value::strings($config['api-tools-mvc-auth']['authentication']['map'] ?? []),
            ]));

            return new OAuth2Adapter(
                oauth2Server: Assert::isInstanceOf($factory(), \OAuth2\Server::class),
                types: $types,
            );
        }

        $factory = new LaminasOAuth2ServerFactory();

        try {
            $serverFactory = $factory(container: $container);
        } catch (RuntimeException $runtimeException) {
            // These are exceptions specifically thrown from the
            // Jield\ApiTools\OAuth2\Factory\OAuth2ServerFactory when essential
            // configuration is missing.
            return match (true) {
                str_contains($runtimeException->getMessage(), 'missing'),
                str_contains($runtimeException->getMessage(), 'string or array'),
                    => false,
                default => throw $runtimeException,
            };
        }

        return new OAuth2Adapter(oauth2Server: Assert::isInstanceOf($serverFactory(), Server::class));
    }

    /**
     * Retrieve custom authentication types
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getAuthenticationTypes(ContainerInterface $container): false|array
    {
        if (!$container->has('config')) {
            return false;
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');
        if (
            ($config['api-tools-mvc-auth']['authentication']['types'] ?? null) === null
            || !is_array(value: $config['api-tools-mvc-auth']['authentication']['types'])
        ) {
            return false;
        }

        return $config['api-tools-mvc-auth']['authentication']['types'];
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getAuthenticationMap(ContainerInterface $container): array
    {
        if (!$container->has('config')) {
            return [];
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');
        if (
            ($config['api-tools-mvc-auth']['authentication']['map'] ?? null) === null
            || !is_array(value: $config['api-tools-mvc-auth']['authentication']['map'])
        ) {
            return [];
        }

        return $config['api-tools-mvc-auth']['authentication']['map'];
    }
}
