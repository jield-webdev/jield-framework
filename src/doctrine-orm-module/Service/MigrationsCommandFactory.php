<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\DoctrineCommand;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;

use function array_key_exists;
use function class_exists;
use function preg_match;
use function ucfirst;

/**
 * Service factory for migrations command
 */
final class MigrationsCommandFactory implements FactoryInterface
{
    private readonly string $commandClassName;

    private string $defaultObjectManagerName = 'doctrine.entitymanager.orm_default';

    public function __construct(string $name)
    {
        // The configuration of migrations looses the case of the command name so mixed
        // case names must be resolved here.
        $name = match ($name) {
            'dumpschema' => 'DumpSchema',
            'syncmetadatastorage' => 'SyncMetadata',
            'uptodate' => 'UpToDate',
            default => ucfirst(string: $name),
        };

        $this->commandClassName = 'Doctrine\Migrations\Tools\Console\Command\\' . $name . 'Command';
    }

    /**
     * {@inheritDoc}
     *
     * @param string $requestedName
     *
     * @return DoctrineCommand
     *
     * @throws InvalidArgumentException
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): DoctrineCommand {
        $matches = [];
        $commandClassName = $this->commandClassName;

        if (!class_exists(class: $commandClassName)) {
            throw new InvalidArgumentException(message: 'The class ' . $commandClassName . ' does not exist');
        }

        $config = $container->get('config');
        $objectManagerName = $this->getObjectManagerName();

        // Copied from DoctrineModule/ServiceFactory/AbstractDoctrineServiceFactory
        if (!preg_match(
            pattern: '/^doctrine\.((?<mappingType>orm|odm)\.|)(?<serviceType>[a-z0-9_]+)\.(?<serviceName>[a-z0-9_]+)$/',
            subject: $objectManagerName,
            matches: $matches,
        )) {
            throw new RuntimeException(message: 'The object manager name is invalid: ' . $objectManagerName);
        }

        /** @var mixed $migrationConfig */
        $migrationConfig = $config['doctrine']['migrations_configuration'][$matches['serviceName']] ?? [];
        $dependencyFactoryServices = [];

        if (array_key_exists(key: 'dependency_factory_services', array: Value::array($migrationConfig))) {
            $dependencyFactoryServices = $migrationConfig['dependency_factory_services'];
            unset($migrationConfig['dependency_factory_services']);
        }

        $dependencyFactory = DependencyFactory::fromEntityManager(
            new ConfigurationArray(Value::stringKeyedArray($migrationConfig)),
            new ExistingEntityManager(Value::instance(
                $container->get($objectManagerName),
                EntityManagerInterface::class,
            )),
        );

        /** @var mixed $id
         * @var mixed $service
         */
        foreach (Value::iterable($dependencyFactoryServices) as $id => $service) {
            $dependencyFactory->setService(
                Value::string($id),
                Value::callback($container->get(Value::string($service))),
            );
        }

        // An object manager may not have a migrations configuration and that's OK.
        // Use default values in that case.
        return Value::instance(
            Value::instantiate($commandClassName, [$dependencyFactory]),
            \Doctrine\Migrations\Tools\Console\Command\DoctrineCommand::class,
        );
    }

    private function getObjectManagerName(): string
    {
        $arguments = new ArgvInput();

        if (!$arguments->hasParameterOption(values: '--object-manager')) {
            return $this->defaultObjectManagerName;
        }

        return Value::string($arguments->getParameterOption(values: '--object-manager'));
    }
}
