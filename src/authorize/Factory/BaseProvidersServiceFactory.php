<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Jield\Authorize\Config;

/**
 * Base factory responsible of instantiating providers
 */
// phpcs:ignore WebimpressCodingStandard.NamingConventions.AbstractClass.Prefix
abstract class BaseProvidersServiceFactory implements FactoryInterface
{
    public const PROVIDER_SETTING = 'providers';

    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): object|array
    {
        $config    = $container->get(Config::class);
        $providers = [];

        foreach ($config[static::PROVIDER_SETTING] as $providerName => $providerConfig) {
            if ($container->has($providerName)) {
                $providers[] = $container->get($providerName);
            } else {
                $providers[] = new $providerName($providerConfig, $container);
            }
        }

        return $providers;
    }
}
