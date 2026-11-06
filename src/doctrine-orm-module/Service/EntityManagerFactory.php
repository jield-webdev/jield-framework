<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\ORM\EntityManager;
use DoctrineModule\Service\AbstractFactory;
use DoctrineORMModule\Options\EntityManager as DoctrineORMModuleEntityManager;
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
        /** @var mixed $connection */
        $connection = $container->get($options->getConnection());
        /** @var mixed $config */
        $config = $container->get($options->getConfiguration());

        // initializing the resolver
        // @todo should actually attach it to a fetched event manager here, and not
        //       rely on its factory code
        $container->get($options->getEntityResolver());

        return new EntityManager(\Jield\Stdlib\Value::instance($connection, \Doctrine\DBAL\Connection::class), \Jield\Stdlib\Value::instance($config, \Doctrine\ORM\Configuration::class));
    }

    public function getOptionsClass(): string
    {
        return DoctrineORMModuleEntityManager::class;
    }
}
