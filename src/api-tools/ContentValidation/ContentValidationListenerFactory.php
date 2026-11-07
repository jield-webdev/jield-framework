<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentValidation;

use Jield\Stdlib\Value;
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
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): ContentValidationListener {
        /** @var mixed $config */
        $config = $container->has('config') ? $container->get('config') : [];
        /** @var mixed $contentValidationConfig */
        $contentValidationConfig = $config['api-tools-content-validation'] ?? [];
        $restServices = $this->getRestServicesFromConfig(config: Value::array($config));

        return new ContentValidationListener(
            config: Value::array($contentValidationConfig),
            inputFilterManager: Value::instance($container->get('InputFilterManager'), ServiceLocatorInterface::class),
            restControllers: $restServices,
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
        if (!isset($config['api-tools-rest'])) {
            return $restServices;
        }

        /** @var mixed $controllerService
         * @var mixed $restConfig
         */
        foreach (Value::iterable($config['api-tools-rest']) as $controllerService => $restConfig) {
            if (!isset($restConfig['route_identifier_name'])) {
                continue;
            }

            $restServices[Value::key($controllerService)] = $restConfig['route_identifier_name'];
        }

        return $restServices;
    }
}
