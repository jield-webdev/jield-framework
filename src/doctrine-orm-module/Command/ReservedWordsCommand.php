<?php

declare(strict_types=1);

namespace DoctrineORMModule\Command;

use Doctrine\DBAL\Tools\Console\ConnectionProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Schema identifier checks for DBAL 4, which removed its legacy command. */
final class ReservedWordsCommand extends Command
{
    public function __construct(
        private readonly ConnectionProvider $connections,
    ) {
        parent::__construct('dbal:reserved-words');
    }

    protected function configure(): void
    {
        $this->setDescription('Check table and column names for reserved SQL words.')->addOption(
            'connection',
            null,
            InputOption::VALUE_REQUIRED,
            'The named database connection',
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @mago-var mixed $name */
        $name = $input->getOption('connection');
        $connection = is_string($name)
            ? $this->connections->getConnection($name)
            : $this->connections->getDefaultConnection();
        $platform = $connection->getDatabasePlatform();
        $rows = [];
        foreach ($connection->createSchemaManager()->introspectSchema()->getTables() as $table) {
            if (SqlKeywords::contains($platform, $table->getObjectName()->getUnqualifiedName()->getValue())) {
                $rows[] = ['Table', $table->getObjectName()->toString()];
            }
            foreach ($table->getColumns() as $column) {
                if (SqlKeywords::contains($platform, $column->getObjectName()->getIdentifier()->getValue())) {
                    $rows[] = [
                        'Column',
                        $table->getObjectName()->toString() . '.' . $column->getObjectName()->toString(),
                    ];
                }
            }
        }
        $io = new SymfonyStyle($input, $output);
        if ($rows === []) {
            $io->success('No reserved words found in schema identifiers.');
            return self::SUCCESS;
        }
        $io->table(['Type', 'Identifier'], $rows);
        return self::FAILURE;
    }
}
