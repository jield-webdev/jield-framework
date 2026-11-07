<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\MvcAuth\Authentication\DefaultAuthenticationListener;
use Jield\ApiTools\MvcAuth\Authentication\HttpAdapter;
use Jield\ApiTools\MvcAuth\Authentication\OAuth2Adapter;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function is_array;
use function is_string;

class AuthenticationAdapterDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * Decorate the DefaultAuthenticationListener.
     *
     * Attaches adapters as listeners if present in configuration.
     *
     * @param  string             $name
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        ?array $options = null,
    ): DefaultAuthenticationListener {
        $listener = $callback();

        $config = $container->get('config');
        if (
            !isset($config['api-tools-mvc-auth']['authentication']['adapters'])
            || !is_array(value: $config['api-tools-mvc-auth']['authentication']['adapters'])
        ) {
            return $listener;
        }

        foreach ($config['api-tools-mvc-auth']['authentication']['adapters'] as $type => $data) {
            $this->attachAdapterOfType(
                type: Assert::string($type),
                adapterConfig: $data,
                container: $container,
                listener: $listener,
            );
        }

        return $listener;
    }

    /**
     * Attach an adaper to the listener as described by $type and $data.
     */
    private function attachAdapterOfType(
        string $type,
        array $adapterConfig,
        ContainerInterface $container,
        DefaultAuthenticationListener $listener,
    ): void {
        if (!isset($adapterConfig['adapter']) || !is_string(value: $adapterConfig['adapter'])) {
            return;
        }

        switch ($adapterConfig['adapter']) {
            case HttpAdapter::class:
                $adapter = AuthenticationHttpAdapterFactory::factory(
                    type: $type,
                    config: $adapterConfig,
                    container: $container,
                );
                break;
            case OAuth2Adapter::class:
                $adapter = AuthenticationOAuth2AdapterFactory::factory(
                    type: $type,
                    config: $adapterConfig,
                    container: $container,
                );
                break;
            default:
                $adapter = false;
                break;
        }

        if (!$adapter) {
            return;
        }

        $listener->attach(adapter: $adapter);
    }
}
