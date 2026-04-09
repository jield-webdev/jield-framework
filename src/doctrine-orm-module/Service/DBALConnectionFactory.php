<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use DoctrineModule\Service\AbstractFactory;
use DoctrineORMModule\Options\DBALConnection;
use PDO;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function array_merge;
use function assert;
use function is_string;

/**
 * DBAL Connection ServiceManager factory
 */
final class DBALConnectionFactory extends AbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param string $requestedName
     *
     * @return Connection
     */
    public function __invoke(ContainerInterface $container, string $requestedName, array|null $options = null): Connection
    {
        $options = $this->getOptions(container: $container, key: 'connection');
        assert(assertion: $options instanceof DBALConnection);
        $pdo = $options->getPdo();

        if (is_string(value: $pdo)) {
            $pdo = $container->get($pdo);
            assert(assertion: $pdo instanceof PDO);
        }

        $params = [
            'driverClass'  => $options->getDriverClass(),
            'wrapperClass' => $options->getWrapperClass(),
            'pdo'          => $pdo,
        ];
        $params = array_merge($params, $options->getParams());

        if (
            array_key_exists(key: 'platform', array: $params)
            && is_string(value: $params['platform'])
            && $container->has(id: $params['platform'])
        ) {
            $params['platform'] = $container->get($params['platform']);
        }

        $configuration = $container->get($options->getConfiguration());
        $eventManager  = $container->get($options->getEventManager());

        $connection = DriverManager::getConnection($params, $configuration, $eventManager);
        foreach ($options->getDoctrineTypeMappings() as $dbType => $doctrineType) {
            $connection->getDatabasePlatform()->registerDoctrineTypeMapping(dbType: $dbType, doctrineType: $doctrineType);
        }

        foreach ($options->getDoctrineCommentedTypes() as $type) {
            $connection->getDatabasePlatform()->markDoctrineTypeCommented(Type::getType(name: $type));
        }

        if ($options->useSavepoints()) {
            $connection->setNestTransactionsWithSavepoints(nestTransactionsWithSavepoints: true);
        }

        return $connection;
    }

    /**
     * Get the class name of the options associated with this factory.
     */
    public function getOptionsClass(): string
    {
        return DBALConnection::class;
    }
}
