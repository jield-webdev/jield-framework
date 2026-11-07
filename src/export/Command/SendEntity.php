<?php

declare(strict_types=1);

namespace Jield\Export\Command;

use Jield\Export\Entity\StorageLocationInterface;
use Jield\Export\Service\ConsoleService;
use Jield\Stdlib\Value;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'export:send')]
final class SendEntity extends Command
{
    public function __construct(
        private readonly ConsoleService $consoleService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $cores = implode(separator: ', ', array: array_merge(
            array_keys(array: $this->consoleService->getEntities()),
            ['all'],
        ));

        $storageLocations = implode(separator: ', ', array: array_map(
            callback: static fn(StorageLocationInterface $storageLocation) => sprintf(
                '%d: %s',
                $storageLocation->getId(),
                $storageLocation->getName(),
            ),
            array: $this->consoleService->getStorageLocationsForExport(),
        ));

        $this->addArgument(name: 'entity', mode: InputOption::VALUE_REQUIRED, description: $cores, default: 'all');

        $this->addArgument(
            name: 'storage-location',
            mode: InputOption::VALUE_REQUIRED,
            description: $storageLocations,
            default: 'all',
        );

        $this->addOption(
            name: 'memory-limit',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Provide a memory limit for the CLI script',
            default: '1G',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $memoryLimit = $input->getOption(name: 'memory-limit');

        ini_set(option: 'memory_limit', value: Value::string($memoryLimit));

        $entity = $input->getArgument(name: 'entity');
        $storageLocation = $input->getArgument(name: 'storage-location');

        $storageLocations = array_filter(
            array: $this->consoleService->getStorageLocationsForExport(),
            callback: static fn(StorageLocationInterface $location) => (
                (string) $location->getId() === (string) $storageLocation
                || $storageLocation === 'all'
            ),
        );

        $startMessage = sprintf(
            '<info>Send entity %s, to: %s</info>',
            Value::scalar($entity),
            implode(', ', array_map(
                callback: static fn(StorageLocationInterface $location) => $location->getName(),
                array: $storageLocations,
            )),
        );
        $memoryMessage = sprintf('Memory limit set to %s', ini_get(option: 'memory_limit'));
        $endMessage = sprintf('<info>Sending %s completed</info>', Value::scalar($entity));

        $output->writeln(messages: $startMessage);
        $output->writeln(messages: $memoryMessage);

        $this->consoleService->sendEntity(
            output: $output,
            entity: Value::string($entity),
            storageLocations: $storageLocations,
        );

        $output->writeln(messages: $endMessage);

        return Command::SUCCESS;
    }
}
