<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Provider\Role\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Jield\Authorize\Config as AuthorizeConfig;

/**
 * Factory responsible of instantiating {@see \Jield\Authorize\Provider\Role\Config}
 */
class ConfigRoleProviderServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Config
    {
        return new Config(
            config: $container->get(AuthorizeConfig::class)['role_providers'][Config::class]
        );
    }
}
