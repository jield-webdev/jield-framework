<?php

declare(strict_types=1);

namespace Jield\Log\Handler;

use Doctrine\DBAL\Connection;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

final class DoctrineDbalHandler extends AbstractProcessingHandler
{
    private Connection $connection;
    private string     $table;

    public function __construct(
        Connection                $connection,
        string                    $table = 'application_log',
        int|string|\Monolog\Level $level = \Monolog\Level::Error,
        bool                      $bubble = true
    )
    {
        $this->connection = $connection;
        $this->table      = $table;

        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $this->connection->insert(
            $this->table,
            [
                'channel'    => $record->channel,
                'level'      => $record->level->value,
                'level_name' => $record->level->getName(),
                'message'    => $record->message,
                'context'    => $this->encode($record->context),
                'extra'      => $this->encode($record->extra),
                'created_at' => $record->datetime->format('Y-m-d H:i:s'),
            ],
            [
                'channel'    => \PDO::PARAM_STR,
                'level'      => \PDO::PARAM_INT,
                'level_name' => \PDO::PARAM_STR,
                'message'    => \PDO::PARAM_STR,
                'context'    => \PDO::PARAM_STR,
                'extra'      => \PDO::PARAM_STR,
                'created_at' => \PDO::PARAM_STR,
            ]
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

        return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
