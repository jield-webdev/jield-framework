<?php

declare(strict_types=1);

namespace Jield\Serializer;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function is_array;

/** @final */
class AdapterPluginManagerFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null
    ): AdapterPluginManager {
        $pluginManager = new AdapterPluginManager(creationContext: $container, config: $options ?? []);

        // If this is in a Jield-mvc application, the ServiceListener will inject
        // merged configuration during bootstrap.
        if ($container->has(id: 'ServiceListener')) {
            return $pluginManager;
        }

        // If we do not have a config service, nothing more to do
        if (! $container->has(id: 'config')) {
            return $pluginManager;
        }

        $config = $container->get('config');

        // If we do not have serializers configuration, nothing more to do
        if (! isset($config['serializers']) || ! is_array(value: $config['serializers'])) {
            return $pluginManager;
        }

        $pluginManager->configure(config: $config['serializers']);

        return $pluginManager;
    }
}
