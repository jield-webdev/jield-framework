<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\HttpMethodListener;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

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
        /** @mago-var mixed $config */
        $config = $container->get('config');

        if (!isset($config['http_methods_listener'])) {
            return new HttpMethodListener();
        }

        /** @mago-var mixed $listenerConfig */
        $listenerConfig = $config['http_methods_listener'];
        /** @mago-var mixed $enabled */
        $enabled = array_key_exists('enabled', Assert::isArray($listenerConfig)) ? $listenerConfig['enabled'] : true;
        $allowedMethods = isset($listenerConfig['allowed_methods']) && is_array($listenerConfig['allowed_methods'])
            ? $listenerConfig['allowed_methods']
            : null;

        return new HttpMethodListener(Assert::boolean($enabled), Assert::isArray($allowedMethods));
    }
}
