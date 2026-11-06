<?php

declare(strict_types=1);

namespace Jield\Export\Command;

use Jield\Export\Service\ConsoleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'export:documentation')]
final class RenderDocumentation extends Command
{
    public function __construct(
        private readonly ConsoleService $consoleService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(messages: '<info>Write documentation</info>');

        $this->consoleService->generateDocumentation(output: $output);

        $output->writeln(messages: '<info>Documentation for all entities has been written</info>');

        return Command::SUCCESS;
    }
}
