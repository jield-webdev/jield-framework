<?php

declare(strict_types=1);

namespace Jield\Form;

use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;

use function is_array;

/** @psalm-import-type ServiceManagerConfiguration from ServiceManager */
final class FormElementManagerFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     * @param string|null $requestedName
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): AbstractPluginManager {
        $configuration = $options ?? [];
        /** @var ServiceManagerConfiguration $configuration */
        $pluginManager = new FormElementManager(creationContext: $container, config: $configuration);

        // If this is in a jield-mvc application, the ServiceListener will inject
        // merged configuration during bootstrap.
        if ($container->has('ServiceListener')) {
            return $pluginManager;
        }

        // If we do not have a config service, nothing more to do
        if (!$container->has('config')) {
            return $pluginManager;
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');

        // If we do not have form_elements configuration, nothing more to do
        if (($config['form_elements'] ?? null) === null || !is_array(value: $config['form_elements'])) {
            return $pluginManager;
        }

        /** @psalm-var ServiceManagerConfiguration $config['form_elements'] */

        // Wire service configuration for forms and elements. Service Manager 4
        // removed Laminas\ServiceManager\Config, so the merged configuration is
        // applied by calling AbstractPluginManager::configure() directly.
        /** @var ServiceManagerConfiguration $managerConfiguration */
        $managerConfiguration = $config['form_elements'];
        $pluginManager->configure(config: $managerConfiguration);

        return $pluginManager;
    }
}
