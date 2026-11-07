<?php

declare(strict_types=1);

namespace Jield\Export\Factory;

use Doctrine\ORM\EntityManager;
use Jield\Export\Options\ModuleOptions;
use Jield\Export\Service\ConsoleService;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class ConsoleServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConsoleService
    {
        return new ConsoleService(
            container: $container,
            entityManager: Service::get($container, EntityManager::class),
            moduleOptions: Service::get($container, ModuleOptions::class),
        );
    }
}
