<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\MvcAuth\Authentication\DefaultAuthenticationListener;
use Jield\ApiTools\MvcAuth\Authentication\HttpAdapter;
use Jield\ApiTools\MvcAuth\Authentication\OAuth2Adapter;
use Laminas\ServiceManager\DelegatorFactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Override;
use Psr\Container\ContainerInterface;

use function is_array;
use function is_string;

class AuthenticationAdapterDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * Decorate the DefaultAuthenticationListener.
     *
     * Attaches adapters as listeners if present in configuration.
     *
     * @param string $name
     */
    #[Override]
    public function __invoke(ContainerInterface $container, string $name, callable $callback, ?array $options = null): DefaultAuthenticationListener
    {
        $listener = $callback();

        $config = $container->get('config');
        if (
            ! isset($config['api-tools-mvc-auth']['authentication']['adapters'])
            || ! is_array(value: $config['api-tools-mvc-auth']['authentication']['adapters'])
        ) {
            return $listener;
        }

        foreach ($config['api-tools-mvc-auth']['authentication']['adapters'] as $type => $data) {
            $this->attachAdapterOfType(type: $type, adapterConfig: $data, container: $container, listener: $listener);
        }

        return $listener;
    }

    /**
     * Decorate the DefaultAuthenticationListener (v2)
     *
     * Provided for backwards compatibility; proxies to __invoke().
     *
     * @param string $name
     * @param string $requestedName
     * @param callable $callback
     */
    #[Override]
    public function createDelegatorWithName(ServiceLocatorInterface $container, string $name, string $requestedName, callable $callback): DefaultAuthenticationListener
    {
        return $this(container: $container, name: $requestedName, callback: $callback);
    }

    /**
     * Attach an adaper to the listener as described by $type and $data.
     */
    private function attachAdapterOfType(
        string $type,
        array $adapterConfig,
        ContainerInterface $container,
        DefaultAuthenticationListener $listener
    ): void {
        if (
            ! isset($adapterConfig['adapter'])
            || ! is_string(value: $adapterConfig['adapter'])
        ) {
            return;
        }

        switch ($adapterConfig['adapter']) {
            case HttpAdapter::class:
                $adapter = AuthenticationHttpAdapterFactory::factory(type: $type, config: $adapterConfig, container: $container);
                break;
            case OAuth2Adapter::class:
                $adapter = AuthenticationOAuth2AdapterFactory::factory(type: $type, config: $adapterConfig, container: $container);
                break;
            default:
                $adapter = false;
                break;
        }

        if (! $adapter) {
            return;
        }

        $listener->attach(adapter: $adapter);
    }
}
