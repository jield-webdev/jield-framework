<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentValidation;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Override;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

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
        /** @mago-var mixed $config */
        $config = $container->has('config') ? $container->get('config') : [];
        /** @mago-var mixed $contentValidationConfig */
        $contentValidationConfig = $config['api-tools-content-validation'] ?? [];
        $restServices = $this->getRestServicesFromConfig(config: Assert::isArray($config));

        return new ContentValidationListener(
            config: Assert::isArray($contentValidationConfig),
            inputFilterManager: Assert::isInstanceOf(
                $container->get('InputFilterManager'),
                ServiceLocatorInterface::class,
            ),
            restControllers: $restServices,
        );
    }

    /**
     * Generate the list of REST services for the listener
     *
     * Looks for api-tools-rest configuration, and creates a list of controller
     * service / identifier name pairs to pass to the listener.
     */
    protected function getRestServicesFromConfig(array $config): array
    {
        $restServices = [];
        if (!isset($config['api-tools-rest'])) {
            return $restServices;
        }

        /**
         * @mago-var mixed $controllerService
         * @mago-var mixed $restConfig
         */
        foreach (Assert::isIterable($config['api-tools-rest']) as $controllerService => $restConfig) {
            if (!isset($restConfig['route_identifier_name'])) {
                continue;
            }

            $restServices[\Jield\Stdlib\Value::key($controllerService)] = $restConfig['route_identifier_name'];
        }

        return $restServices;
    }
}
