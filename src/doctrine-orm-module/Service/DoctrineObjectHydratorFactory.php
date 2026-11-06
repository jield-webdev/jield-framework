<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class DoctrineObjectHydratorFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DoctrineObject
    {
        return new DoctrineObject(\Jield\Stdlib\Service::get($container, \Doctrine\ORM\EntityManagerInterface::class, 'doctrine.entitymanager.orm_default'));
    }
}
