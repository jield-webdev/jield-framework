<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use DoctrineORMModule\Command\ReservedWordsCommand;
use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class ReservedWordsCommandFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): ReservedWordsCommand {
        return new ReservedWordsCommand(
            new SingleConnectionProvider(connection: \Jield\Stdlib\Service::get($container, \Doctrine\DBAL\Connection::class, 'doctrine.connection.orm_default')),
        );
    }
}
