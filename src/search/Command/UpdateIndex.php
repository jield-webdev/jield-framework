<?php

declare(strict_types=1);

namespace Jield\Search\Command;

use Jield\Search\Service\ConsoleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(name: 'search:update-index', description: 'Update the search engine index')]
final class UpdateIndex extends Command
{
    public function __construct(
        private readonly ConsoleService $consoleService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $cores = implode(separator: ', ', array: array_merge(
            array_keys(array: $this->consoleService->getCores()),
            ['all'],
        ));
        $this->addArgument(name: 'index', mode: InputOption::VALUE_REQUIRED, description: $cores, default: 'all');

        $this->addOption(name: 'reset', shortcut: 'r', mode: InputOption::VALUE_NONE, description: 'Reset index');
        $this->addOption(
            name: 'shallow',
            shortcut: 's',
            mode: InputOption::VALUE_NONE,
            description: 'Shallow update index',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var mixed $index */
        $index = $input->getArgument(name: 'index');
        /** @var mixed $reset */
        $reset = $input->getOption(name: 'reset');
        /** @var mixed $shallow */
        $shallow = $input->getOption(name: 'shallow');

        $startMessage = sprintf('<info>%s the index of %s</info>', $reset ? 'Reset' : 'Update', \Jield\Stdlib\Value::scalar($index));
        $output->writeln(messages: $startMessage);

        if ($shallow) {
            $output->writeln(messages: '<info>Shallow update enabled</info>');
        }

        $this->consoleService->resetIndex(output: $output, index: \Jield\Stdlib\Value::string($index), clearIndex: \Jield\Stdlib\Value::bool($reset), shallow: \Jield\Stdlib\Value::bool($shallow));

        $endMessage = sprintf('<info>%s the index of %s completed</info>', $reset ? 'Reset' : 'Update', \Jield\Stdlib\Value::scalar($index));
        $output->writeln(messages: $endMessage);

        return Command::SUCCESS;
    }
}
