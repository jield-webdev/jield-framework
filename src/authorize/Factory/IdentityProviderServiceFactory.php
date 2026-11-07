<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class IdentityProviderServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        return $container->get(Assert::string(Assert::isArray($container->get(Config::class))['identity_provider']));
    }
}
