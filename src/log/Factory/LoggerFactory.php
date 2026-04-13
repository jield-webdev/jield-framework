<?php

declare(strict_types=1);

namespace Jield\Log\Factory;

use Jield\Log\Handler\DoctrineDbalHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;

final class LoggerFactory
{
    public function __invoke(ContainerInterface $container): Logger
    {
        $config = $this->getConfig($container);
        $loggerName = $config['default_logger'] ?? 'app';

        return $this->createLogger(
            container: $container,
            config: $config,
            loggerName: $loggerName
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public function createLogger(ContainerInterface $container, array $config, string $loggerName): Logger
    {
        $loggerConfig = $config['loggers'][$loggerName] ?? ['name' => $loggerName, 'handlers' => []];
        $name = (string) ($loggerConfig['name'] ?? $loggerName);

        $logger = new Logger($name);

        $handlers = $loggerConfig['handlers'] ?? [];
        foreach ($handlers as $handlerName) {
            $handlerConfig = $config['handlers'][$handlerName] ?? null;
            if (!is_array($handlerConfig)) {
                continue;
            }

            $handler = $this->createHandler($container, $handlerConfig);
            if ($handler instanceof HandlerInterface) {
                $logger->pushHandler($handler);
            }
        }

        return $logger;
    }

    /**
     * @param array<string, mixed> $handlerConfig
     */
    private function createHandler(ContainerInterface $container, array $handlerConfig): ?HandlerInterface
    {
        $type = $handlerConfig['type'] ?? null;
        if (!is_string($type)) {
            return null;
        }

        $options = $handlerConfig['options'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }

        return match ($type) {
            'stream' => $this->createStreamHandler($options),
            'doctrine_dbal' => $this->createDoctrineDbalHandler($container, $options),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createStreamHandler(array $options): HandlerInterface
    {
        $stream = (string) ($options['stream'] ?? 'data/log/app.log');
        $level = $options['level'] ?? 'debug';
        $bubble = (bool) ($options['bubble'] ?? true);

        $handler = new StreamHandler($stream, $level, $bubble);
        $formatter = new LineFormatter();
        $handler->setFormatter($formatter);

        return $handler;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createDoctrineDbalHandler(ContainerInterface $container, array $options): HandlerInterface
    {
        $connectionService = (string) ($options['connection'] ?? 'doctrine.connection.orm_default');
        $table = (string) ($options['table'] ?? 'application_log');
        $level = $options['level'] ?? 'error';
        $bubble = (bool) ($options['bubble'] ?? true);

        $connection = $container->get($connectionService);

        return new DoctrineDbalHandler(
            connection: $connection,
            table: $table,
            level: $level,
            bubble: $bubble
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(ContainerInterface $container): array
    {
        $config = $container->has('config') ? $container->get('config') : [];
        if (!is_array($config)) {
            return [];
        }

        $monologConfig = $config['monolog'] ?? [];
        if (!is_array($monologConfig)) {
            return [];
        }

        return $monologConfig;
    }
}
