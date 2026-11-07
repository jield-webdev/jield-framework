<?php

declare(strict_types=1);

namespace Jield\Export\Command;

use Jield\Export\Entity\StorageLocationInterface;
use Jield\Export\Service\ConsoleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

#[AsCommand(name: 'export:send')]
final class SendEntity extends Command
{
    public function __construct(
        private readonly ConsoleService $consoleService,
    ) {
        parent::__construct();
    }

    /**
     * @throws \UnexpectedValueException
     * @throws \ReflectionException
     */
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

    /**
     * @throws \UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     * @throws \JsonException
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @mago-var mixed $memoryLimit */
        $memoryLimit = $input->getOption(name: 'memory-limit');

        // This adapter deliberately configures the current process for its documented runtime behavior.
        // @mago-expect lint:no-ini-set(1)
        ini_set(option: 'memory_limit', value: Assert::string($memoryLimit));

        /** @mago-var mixed $entity */
        $entity = $input->getArgument(name: 'entity');
        /** @mago-var mixed $storageLocation */
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
            \Jield\Stdlib\Value::scalar($entity),
            implode(', ', array_map(
                callback: static fn(StorageLocationInterface $location) => $location->getName(),
                array: $storageLocations,
            )),
        );
        $memoryMessage = sprintf('Memory limit set to %s', ini_get(option: 'memory_limit'));
        $endMessage = sprintf('<info>Sending %s completed</info>', \Jield\Stdlib\Value::scalar($entity));

        $output->writeln(messages: $startMessage);
        $output->writeln(messages: $memoryMessage);

        $this->consoleService->sendEntity(
            output: $output,
            entity: Assert::string($entity),
            storageLocations: $storageLocations,
        );

        $output->writeln(messages: $endMessage);

        return Command::SUCCESS;
    }
}
