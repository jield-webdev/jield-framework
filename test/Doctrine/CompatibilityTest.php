<?php

declare(strict_types=1);

namespace JieldTest\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use DoctrineORMModule\Command\ReservedWordsCommand;
use Jield\ApiTools\Paginator\DoctrineORMAdapter;
use Jield\ApiTools\Provider\ProviderInterface;
use JieldTest\Doctrine\TestAsset\PaginationEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CompatibilityTest extends TestCase
{
    public function testPaginatorKeepsTotalCountAcrossWindows(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/TestAsset'], true);
        $configuration->enableNativeLazyObjects(true);
        $manager = new EntityManager($connection, $configuration);
        new SchemaTool($manager)->createSchema([$manager->getClassMetadata(PaginationEntity::class)]);
        foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
            $manager->persist(new PaginationEntity($name));
        }
        $manager->flush();
        $adapter = new DoctrineORMAdapter(
            $manager->createQuery('SELECT e FROM ' . PaginationEntity::class . ' e ORDER BY e.name'),
        );
        $adapter->setProvider(new class implements ProviderInterface {
            public function generateArray($entity): array
            {
                return ['name' => $entity->name];
            }
        });
        self::assertSame(3, $adapter->count());
        self::assertSame([['name' => 'Bravo']], $adapter->getItems(1, 1));
        self::assertSame(3, $adapter->count());
        self::assertSame([['name' => 'Charlie']], $adapter->getItems(2, 2));
    }

    public function testReservedWordCommandChecksTablesAndColumns(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE "select" ("from" INTEGER)');
        $tester = new CommandTester(new ReservedWordsCommand(new SingleConnectionProvider($connection)));
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('select', $tester->getDisplay());
        self::assertStringContainsString('from', $tester->getDisplay());
        $connection->executeStatement('DROP TABLE "select"');
        $connection->executeStatement('CREATE TABLE users (id INTEGER)');
        self::assertSame(0, $tester->execute([]));
    }
}
