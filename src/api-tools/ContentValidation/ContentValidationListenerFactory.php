<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentValidation;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Override;
use Psr\Container\ContainerInterface;

class ContentValidationListenerFactory implements FactoryInterface
{
    /**
     * Create and return a ContentValidationListener instance.
     *
     * @param string $requestedName
     */
    #[Override]
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): ContentValidationListener
    {
        $config                  = $container->has(id: 'config') ? $container->get('config') : [];
        $contentValidationConfig = $config['api-tools-content-validation'] ?? [];
        $restServices            = $this->getRestServicesFromConfig(config: $config);

        return new ContentValidationListener(
            config: $contentValidationConfig,
            inputFilterManager: $container->get('InputFilterManager'),
            restControllers: $restServices
        );
    }

    /**
     * Generate the list of REST services for the listener
     *
     * Looks for api-tools-rest configuration, and creates a list of controller
     * service / identifier name pairs to pass to the listener.
     *
     * @param array $config
     * @return array
     */
    protected function getRestServicesFromConfig(array $config): array
    {
        $restServices = [];
        if (! isset($config['api-tools-rest'])) {
            return $restServices;
        }

        foreach ($config['api-tools-rest'] as $controllerService => $restConfig) {
            if (! isset($restConfig['route_identifier_name'])) {
                continue;
            }

            $restServices[$controllerService] = $restConfig['route_identifier_name'];
        }

        return $restServices;
    }
}
