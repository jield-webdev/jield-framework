<?php

declare(strict_types=1);

namespace Jield\Log\Handler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use UnhandledMatchError;

final class DoctrineDbalHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = 'application_log',
        int|string|Level $level = Level::Error,
        bool $bubble = true,
    ) {
        $level = $level instanceof Level
            ? $level
            : (
                is_int($level)
                    ? Level::from($level)
                    : match (strtolower($level)) {
                        'debug' => Level::Debug,
                        'info' => Level::Info,
                        'notice' => Level::Notice,
                        'warning' => Level::Warning,
                        'error' => Level::Error,
                        'critical' => Level::Critical,
                        'alert' => Level::Alert,
                        'emergency' => Level::Emergency,
                        default => throw new UnhandledMatchError(sprintf('Unknown log level: %s', $level)),
                    }
            );
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
                'channel' => ParameterType::STRING,
                'level' => ParameterType::INTEGER,
                'level_name' => ParameterType::STRING,
                'message' => ParameterType::STRING,
                'context' => ParameterType::STRING,
                'extra' => ParameterType::STRING,
                'created_at' => ParameterType::STRING,
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
