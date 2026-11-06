<?php

declare(strict_types=1);

namespace Jield\Search\Factory;

use Doctrine\ORM\EntityManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class UpdateSearchHandlerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        return \Jield\Stdlib\Value::instantiate($requestedName, ['container' => $container, 'entityManager' => \Jield\Stdlib\Service::get($container, EntityManager::class)]);
    }
}
