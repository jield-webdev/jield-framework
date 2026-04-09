<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\Authentication\Adapter\Http as HttpAuth;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating the DefaultAuthHttpAdapterFactory from configuration.
 */
class DefaultAuthHttpAdapterFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param string $requestedName
     */
    #[Override]
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): false|HttpAuth
    {
        // If no configuration present, nothing to create
        if (! $container->has(id: 'config')) {
            return false;
        }

        $config = $container->get('config');

        // If no HTTP adapter configuration present, nothing to create
        if (! isset($config['api-tools-mvc-auth']['authentication']['http'])) {
            return false;
        }

        return HttpAdapterFactory::factory(config: $config['api-tools-mvc-auth']['authentication']['http'], container: $container);
    }
}
