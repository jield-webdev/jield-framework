<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\MvcAuth\Authentication\DefaultAuthenticationListener;
use Jield\ApiTools\MvcAuth\Authentication\HttpAdapter;
use Jield\ApiTools\MvcAuth\Authentication\OAuth2Adapter;
use Jield\ApiTools\OAuth2\Factory\OAuth2ServerFactory as LaminasOAuth2ServerFactory;
use Laminas\Authentication\Adapter\Http;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function is_array;
use function is_string;
use function strpos;

/**
 * Factory for creating the DefaultAuthenticationListener from configuration.
 */
class DefaultAuthenticationListenerFactory implements FactoryInterface
{
    /**
     * Create and return a DefaultAuthenticationListener.
     *
     * @param string $requestedName
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
     * @param ContainerInterface $container
     * @return false|HttpAdapter
     */
    protected function retrieveHttpAdapter(ContainerInterface $container): false|HttpAdapter
    {
        // Allow applications to provide their own AuthHttpAdapter service; if none provided,
        // or no HTTP adapter configuration provided to api-tools-mvc-auth, we can stop early.

        /** @var mixed $httpAdapter */
        $httpAdapter = $container->get(Http::class);

        if ($httpAdapter === false) {
            return false;
        }

        // We must abort if no resolver was provided
        if (!$httpAdapter->getBasicResolver() && !$httpAdapter->getDigestResolver()) {
            return false;
        }

        $authService = \Jield\Stdlib\Service::get($container, \Laminas\Authentication\AuthenticationServiceInterface::class, 'authentication');

        return new HttpAdapter(httpAuth: \Jield\Stdlib\Value::instance($httpAdapter, \Laminas\Authentication\Adapter\Http::class), authenticationService: $authService);
    }

    /**
     * Create an OAuth2 server by introspecting the config service
     *
     */
    protected function createOAuth2Server(ContainerInterface $container): false|OAuth2Adapter
    {
        if (!$container->has('config')) {
            // If we don't have configuration, we cannot create an OAuth2 server.
            return false;
        }

        /** @var mixed $config */
        $config = $container->get('config');
        if (
            !isset($config['api-tools-oauth2']['storage'])
            || !is_string(value: $config['api-tools-oauth2']['storage'])
            || !$container->has($config['api-tools-oauth2']['storage'])
        ) {
            return false;
        }

        if ($container->has('Jield\ApiTools\OAuth2\Service\OAuth2Server')) {
            // If the service locator already has a pre-configured OAuth2 server, use it.
            /** @var mixed $factory */
            $factory = $container->get('Jield\ApiTools\OAuth2\Service\OAuth2Server');

            $types = array_values(array_unique(array_merge(
                ['oauth2'],
                $config['api-tools-mvc-auth']['authentication']['map'] ?? [],
            )));

            return new OAuth2Adapter(oauth2Server: \Jield\Stdlib\Value::instance($factory(), \OAuth2\Server::class), types: $types);
        }

        $factory = new LaminasOAuth2ServerFactory();

        try {
            $serverFactory = $factory(container: $container);
        } catch (RuntimeException $runtimeException) {
            // These are exceptions specifically thrown from the
            // Jield\ApiTools\OAuth2\Factory\OAuth2ServerFactory when essential
            // configuration is missing.
            return match (true) {
                strpos(haystack: $runtimeException->getMessage(), needle: 'missing') => false,
                strpos(haystack: $runtimeException->getMessage(), needle: 'string or array') => false,
                default => throw $runtimeException,
            };
        }

        return new OAuth2Adapter(oauth2Server: \Jield\Stdlib\Value::instance($serverFactory(), \OAuth2\Server::class));
    }

    /**
     * Retrieve custom authentication types
     *
     */
    protected function getAuthenticationTypes(ContainerInterface $container): false|array
    {
        if (!$container->has('config')) {
            return false;
        }

        /** @var mixed $config */
        $config = $container->get('config');
        if (
            !isset($config['api-tools-mvc-auth']['authentication']['types'])
            || !is_array(value: $config['api-tools-mvc-auth']['authentication']['types'])
        ) {
            return false;
        }

        return $config['api-tools-mvc-auth']['authentication']['types'];
    }

    protected function getAuthenticationMap(ContainerInterface $container): array
    {
        if (!$container->has('config')) {
            return [];
        }

        /** @var mixed $config */
        $config = $container->get('config');
        if (
            !isset($config['api-tools-mvc-auth']['authentication']['map'])
            || !is_array(value: $config['api-tools-mvc-auth']['authentication']['map'])
        ) {
            return [];
        }

        return $config['api-tools-mvc-auth']['authentication']['map'];
    }
}
