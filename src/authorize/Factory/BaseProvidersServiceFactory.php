<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

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
        $config = \Jield\Stdlib\Value::array($container->get(Config::class));
        $providers = [];

        /** @var mixed $providerName
         * @var mixed $providerConfig
         */
        foreach (\Jield\Stdlib\Value::iterable($config[static::PROVIDER_SETTING]) as $providerName => $providerConfig) {
            if ($container->has(\Jield\Stdlib\Value::string($providerName))) {
                $providers[] = $container->get(\Jield\Stdlib\Value::string($providerName));
            } else {
                $providers[] = \Jield\Stdlib\Value::instantiate($providerName, [$providerConfig, $container]);
            }
        }

        return $providers;
    }
}
