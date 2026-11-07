<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

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
        $config = Assert::isArray($container->get(Config::class));
        $providers = [];

        /**
         * @mago-var mixed $providerName
         * @mago-var mixed $providerConfig
         */
        foreach (Assert::isIterable($config[static::PROVIDER_SETTING]) as $providerName => $providerConfig) {
            if ($container->has(Assert::string($providerName))) {
                $providers[] = $container->get(Assert::string($providerName));
            } else {
                $providers[] = Value::instantiate($providerName, [$providerConfig, $container]);
            }
        }

        return $providers;
    }
}
