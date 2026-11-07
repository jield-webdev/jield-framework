<?php

declare(strict_types=1);

namespace Jield\Export\Factory;

use Jield\Export\Options\ModuleOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class ModuleOptionsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ModuleOptions
    {
        $config = $container->get('Config');

        return new ModuleOptions(options: $config['jield_export'] ?? []);
    }
}
