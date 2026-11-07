<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
use DoctrineORMModule\Command\ReservedWordsCommand;
use Jield\Stdlib\Service;
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
            new SingleConnectionProvider(connection: Service::get(
                $container,
                Connection::class,
                'doctrine.connection.orm_default',
            )),
        );
    }
}
