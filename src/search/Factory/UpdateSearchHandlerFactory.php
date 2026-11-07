<?php

declare(strict_types=1);

namespace Jield\Search\Factory;

use Doctrine\ORM\EntityManager;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class UpdateSearchHandlerFactory implements FactoryInterface
{
    /**
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        return Value::instantiate($requestedName, [
            'container' => $container,
            'entityManager' => Service::get($container, EntityManager::class),
        ]);
    }
}
