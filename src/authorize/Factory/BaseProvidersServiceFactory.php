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
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
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
            $providers[] = $container->has(Assert::string($providerName))
                ? $container->get(Assert::string($providerName))
                : Value::instantiate($providerName, [$providerConfig, $container]);
        }

        return $providers;
    }
}
