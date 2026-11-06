<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config as AuthorizeConfig;
use Jield\Authorize\Provider\Rule\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory responsible of instantiating {@see \Jield\Authorize\Provider\Rule\Config}
 */
class ConfigRuleProviderServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Config
    {
        return new Config(config: \Jield\Stdlib\Value::array(\Jield\Stdlib\Value::path($container->get(AuthorizeConfig::class), ['rule_providers', Config::class])));
    }
}
