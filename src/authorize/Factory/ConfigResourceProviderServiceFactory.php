<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Provider\Resource\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Jield\Authorize\Config as AuthorizeConfig;

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
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Config
    {
        return new Config(
            config: $container->get(AuthorizeConfig::class)['resource_providers'][Config::class]
        );
    }
}
