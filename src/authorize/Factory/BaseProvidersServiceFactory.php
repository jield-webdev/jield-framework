<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Jield\Stdlib\Value;
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
        $config = Value::array($container->get(Config::class));
        $providers = [];

        /** @var mixed $providerName
         * @var mixed $providerConfig
         */
        foreach (Value::iterable($config[static::PROVIDER_SETTING]) as $providerName => $providerConfig) {
            if ($container->has(Value::string($providerName))) {
                $providers[] = $container->get(Value::string($providerName));
            } else {
                $providers[] = Value::instantiate($providerName, [$providerConfig, $container]);
            }
        }

        return $providers;
    }
}
