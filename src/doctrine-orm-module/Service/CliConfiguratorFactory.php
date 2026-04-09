<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use DoctrineORMModule\CliConfigurator;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class CliConfiguratorFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(ContainerInterface $container, $requestedName, array|null $options = null): CliConfigurator
    {
        return new CliConfigurator(container: $container);
    }
}
