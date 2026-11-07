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
     * @throws \RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): EntityManager {
        $options = $this->getOptions(container: $container, key: 'entitymanager');
        assert(assertion: $options instanceof DoctrineORMModuleEntityManager);
        /** @mago-var mixed $connection */
        $connection = $container->get($options->getConnection());
        /** @mago-var mixed $config */
        $config = $container->get($options->getConfiguration());

        // initializing the resolver
        // @todo should actually attach it to a fetched event manager here, and not
        //       rely on its factory code
        $container->get($options->getEntityResolver());

        return new EntityManager(\Webmozart\Assert\Assert::isInstanceOf($connection, \Doctrine\DBAL\Connection::class), \Webmozart\Assert\Assert::isInstanceOf($config, \Doctrine\ORM\Configuration::class));
    }

    public function getOptionsClass(): string
    {
        return DoctrineORMModuleEntityManager::class;
    }
}
