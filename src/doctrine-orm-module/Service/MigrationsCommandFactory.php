<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\DoctrineCommand;
use InvalidArgumentException;
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
    private string $commandClassName;

    private string $defaultObjectManagerName = 'doctrine.entitymanager.orm_default';

    public function __construct(string $name)
    {
        // The configuration of migrations looses the case of the command name so mixed
        // case names must be resolved here.
        switch ($name) {
            case 'dumpschema':
                $name = 'DumpSchema';
                break;
            case 'syncmetadatastorage':
                $name = 'SyncMetadata';
                break;
            case 'uptodate':
                $name = 'UpToDate';
                break;
            default:
                $name = ucfirst(string: $name);
                break;
        }

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
    public function __invoke(ContainerInterface $container, string $requestedName, array|null $options = null): DoctrineCommand
    {
        $commandClassName = $this->commandClassName;

        if (! class_exists(class: $commandClassName)) {
            throw new InvalidArgumentException(message: 'The class ' . $commandClassName . ' does not exist');
        }

        $config            = $container->get('config');
        $objectManagerName = $this->getObjectManagerName();

        // Copied from DoctrineModule/ServiceFactory/AbstractDoctrineServiceFactory
        if (
            ! preg_match(
                pattern: '/^doctrine\.((?<mappingType>orm|odm)\.|)(?<serviceType>[a-z0-9_]+)\.(?<serviceName>[a-z0-9_]+)$/',
                subject: $objectManagerName,
                matches: $matches,
            )
        ) {
            throw new RuntimeException(message: 'The object manager name is invalid: ' . $objectManagerName);
        }

        $migrationConfig           = $config['doctrine']['migrations_configuration'][$matches['serviceName']] ?? [];
        $dependencyFactoryServices = [];

        if (array_key_exists(key: 'dependency_factory_services', array: $migrationConfig)) {
            $dependencyFactoryServices = $migrationConfig['dependency_factory_services'];
            unset($migrationConfig['dependency_factory_services']);
        }

        $dependencyFactory = DependencyFactory::fromEntityManager(
            new ConfigurationArray($migrationConfig),
            new ExistingEntityManager($container->get($objectManagerName)),
        );

        foreach ($dependencyFactoryServices as $id => $service) {
            $dependencyFactory->setService($id, $container->get($service));
        }

        // An object manager may not have a migrations configuration and that's OK.
        // Use default values in that case.
        return new $commandClassName($dependencyFactory);
    }

    private function getObjectManagerName(): string
    {
        $arguments = new ArgvInput();

        if (! $arguments->hasParameterOption(values: '--object-manager')) {
            return $this->defaultObjectManagerName;
        }

        return $arguments->getParameterOption(values: '--object-manager');
    }
}
