<?php

declare(strict_types=1);

namespace Jield\Export\Command;

use Jield\Export\Service\ConsoleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'search:list')]
final class ListEntities extends Command
{
    public function __construct(
        private readonly ConsoleService $consoleService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(messages: '<info>List of all entities in index</info>');
        $entities = $this->consoleService->getEntities();

        /** @var mixed $entityInfo */
        foreach ($entities as $key => $entityInfo) {
            //Try to instantiate the core and see if we have a valid entity
            $output->writeln(messages: sprintf(
                'Entity for %s (%s) is active: (%s)',
                $key,
                key($entityInfo),
                implode(', ', \Jield\Stdlib\Value::scalars($entityInfo)),
            ));
        }

        $output->writeln(messages: sprintf('<info>In total %d entities are active</info>', count($entities)));

        return Command::SUCCESS;
    }
}
