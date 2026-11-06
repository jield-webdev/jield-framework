<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Twig\ModuleOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ModuleOptionsFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ModuleOptions {
        /** @var mixed $config */
        $config = $container->get('config');

        return new ModuleOptions(\Jield\Stdlib\Value::stringKeyedArray($config['jield_twig'] ?? []));
    }
}
