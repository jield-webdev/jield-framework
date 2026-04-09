<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Authorize\Provider\Resource\Config;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory responsible of instantiating {@see \Jield\Authorize\Provider\Resource\Config}
 */
class ConfigResourceProviderServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): object|Config
    {
        return new Config(
            config: $container->get('Jield\Authorize\Config')['resource_providers'][Config::class]
        );
    }
}
