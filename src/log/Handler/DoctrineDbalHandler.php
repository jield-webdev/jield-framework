<?php

declare(strict_types=1);

namespace Jield\Log\Handler;

use Doctrine\DBAL\Connection;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

final class DoctrineDbalHandler extends AbstractProcessingHandler
{
    private Connection $connection;
    private string $table;

    public function __construct(
        Connection $connection,
        string $table = 'application_log',
        int|string|\Monolog\Level $level = \Monolog\Level::Error,
        bool $bubble = true,
    ) {
        $this->connection = $connection;
        $this->table = $table;

        $level = $level instanceof \Monolog\Level ? $level
            : (is_int($level) ? \Monolog\Level::from($level) : \Monolog\Level::fromName($level));
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $this->connection->insert(
            $this->table,
            [
                'channel' => $record->channel,
                'level' => $record->level->value,
                'level_name' => $record->level->getName(),
                'message' => $record->message,
                'context' => $this->encode($record->context),
                'extra' => $this->encode($record->extra),
                'created_at' => $record->datetime->format('Y-m-d H:i:s'),
            ],
            [
                'channel' => \Doctrine\DBAL\ParameterType::STRING,
                'level' => \Doctrine\DBAL\ParameterType::INTEGER,
                'level_name' => \Doctrine\DBAL\ParameterType::STRING,
                'message' => \Doctrine\DBAL\ParameterType::STRING,
                'context' => \Doctrine\DBAL\ParameterType::STRING,
                'extra' => \Doctrine\DBAL\ParameterType::STRING,
                'created_at' => \Doctrine\DBAL\ParameterType::STRING,
            ],
        );
    }

    /**
     * @param array<mixed> $data
     */
    private function encode(array $data): string
    {
        if ($data === []) {
            return '{}';
        }

        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
