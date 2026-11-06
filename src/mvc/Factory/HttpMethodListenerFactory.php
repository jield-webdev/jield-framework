<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\HttpMethodListener;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function is_array;

class HttpMethodListenerFactory implements FactoryInterface
{
    /**
     * {@inheritdoc}
     *
     * @return HttpMethodListener
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): HttpMethodListener {
        /** @var mixed $config */
        $config = $container->get('config');

        if (!isset($config['http_methods_listener'])) {
            return new HttpMethodListener();
        }

        /** @var mixed $listenerConfig */
        $listenerConfig = $config['http_methods_listener'];
        /** @var mixed $enabled */
        $enabled = array_key_exists('enabled', \Jield\Stdlib\Value::array($listenerConfig)) ? $listenerConfig['enabled'] : true;
        $allowedMethods = isset($listenerConfig['allowed_methods']) && is_array($listenerConfig['allowed_methods'])
            ? $listenerConfig['allowed_methods']
            : null;

        return new HttpMethodListener(\Jield\Stdlib\Value::bool($enabled), \Jield\Stdlib\Value::array($allowedMethods));
    }
}
