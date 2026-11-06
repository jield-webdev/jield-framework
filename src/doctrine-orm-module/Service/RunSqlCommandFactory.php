<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class RunSqlCommandFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RunSqlCommand
    {
        return new RunSqlCommand(
            connectionProvider: new SingleConnectionProvider(connection: \Jield\Stdlib\Service::get($container, \Doctrine\DBAL\Connection::class, 'doctrine.connection.orm_default')),
        );
    }
}
