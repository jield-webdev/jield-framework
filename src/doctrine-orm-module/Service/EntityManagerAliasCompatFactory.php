<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

/**
 * Factory that provides the `Doctrine\ORM\EntityManager` alias for `doctrine.entitymanager.orm_default`
 */
final class EntityManagerAliasCompatFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     *
     *@deprecated this method was introduced to allow aliasing of service `Doctrine\ORM\EntityManager`
     *             from `doctrine.entitymanager.orm_default`
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): EntityManager {
        return Assert::isInstanceOf(
            Service::get($container, EntityManagerInterface::class, 'doctrine.entitymanager.orm_default'),
            \Doctrine\ORM\EntityManager::class,
        );
    }
}
