<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use DoctrineModule\Service\AbstractFactory;
use DoctrineORMModule\Options\EntityManager as DoctrineORMModuleEntityManager;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

use function assert;

final class EntityManagerFactory extends AbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param string $requestedName
     *
     * @return EntityManager
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): EntityManager {
        $options = $this->getOptions(container: $container, key: 'entitymanager');
        assert(assertion: $options instanceof DoctrineORMModuleEntityManager);
        $connection = $container->get($options->getConnection());
        $config = $container->get($options->getConfiguration());

        // initializing the resolver
        // @todo should actually attach it to a fetched event manager here, and not
        //       rely on its factory code
        $container->get($options->getEntityResolver());

        return new EntityManager(
            Value::instance($connection, Connection::class),
            Value::instance($config, Configuration::class),
        );
    }

    public function getOptionsClass(): string
    {
        return DoctrineORMModuleEntityManager::class;
    }
}
