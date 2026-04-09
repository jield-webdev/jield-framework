<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Jield\Mvc\HttpMethodListener;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function array_key_exists;
use function is_array;

class HttpMethodListenerFactory implements FactoryInterface
{
    /**
     * {@inheritdoc}
     *
     * @return HttpMethodListener
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $config = $container->get('config');

        if (! isset($config['http_methods_listener'])) {
            return new HttpMethodListener();
        }

        $listenerConfig = $config['http_methods_listener'];
        $enabled        = array_key_exists(key: 'enabled', array: $listenerConfig)
            ? $listenerConfig['enabled']
            : true;
        $allowedMethods = isset($listenerConfig['allowed_methods']) && is_array(value: $listenerConfig['allowed_methods'])
            ? $listenerConfig['allowed_methods']
            : null;

        return new HttpMethodListener(enabled: $enabled, allowedMethods: $allowedMethods);
    }
}
