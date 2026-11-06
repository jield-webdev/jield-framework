<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

final class ViewHelperAbstractFactory implements AbstractFactoryInterface
{
    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        return (
            (
                str_starts_with($requestedName, 'Laminas\\View\\Helper\\')
                || str_starts_with($requestedName, 'Jield\\Bootstrap5\\Form\\View\\Helper\\')
            )
            && \Jield\Stdlib\Service::get($container, \Laminas\View\HelperPluginManager::class, 'ViewHelperManager')->has($requestedName)
        );
    }

    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        return \Jield\Stdlib\Service::get($container, \Laminas\View\HelperPluginManager::class, 'ViewHelperManager')->get($requestedName);
    }
}
