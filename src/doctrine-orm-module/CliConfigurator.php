<?php

declare(strict_types=1);

namespace DoctrineORMModule;

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;

/** Registers the ORM 3 / DBAL 4 commands with an entity-manager provider. */
class CliConfigurator
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function configure(Application $cli): void
    {
        $arguments = new ArgvInput();
        $managerName = $arguments->getParameterOption('--object-manager', 'doctrine.entitymanager.orm_default');
        $provider = new SingleManagerProvider($this->container->get($managerName), $managerName);
        ConsoleRunner::addCommands($cli, $provider);
        foreach ($cli->all() as $command) {
            $command
                ->getDefinition()
                ->addOption(
                    new InputOption(
                        'object-manager',
                        null,
                        InputOption::VALUE_OPTIONAL,
                        'The container service name of the entity manager.',
                        $managerName,
                    ),
                );
        }
    }
}
