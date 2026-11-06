<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\ORM\EntityManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory that provides the `Doctrine\ORM\EntityManager` alias for `doctrine.entitymanager.orm_default`
 */
final class EntityManagerAliasCompatFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @param string $requestedName
     *
     * @return EntityManager
     *@deprecated this method was introduced to allow aliasing of service `Doctrine\ORM\EntityManager`
     *             from `doctrine.entitymanager.orm_default`
     *
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): EntityManager {
        return \Jield\Stdlib\Value::instance(\Jield\Stdlib\Service::get($container, \Doctrine\ORM\EntityManagerInterface::class, 'doctrine.entitymanager.orm_default'), \Doctrine\ORM\EntityManager::class);
    }
}
