<?php

declare(strict_types=1);

namespace Jield\Log\Factory;

use Doctrine\DBAL\Connection;
use Jield\Log\Handler\DoctrineDbalHandler;
use Jield\Stdlib\Service;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;

/**
 * @psalm-type HandlerOptions = array{stream?: string, level?: int|string|Level, bubble?: bool, connection?: string, table?: string}
 * @psalm-type HandlerConfig = array{type?: string, options?: HandlerOptions}
 * @psalm-type MonologConfig = array{default_logger?: string, loggers?: array<string, array{name?: string, handlers?: list<string>}>, handlers?: array<string, HandlerConfig>}
 */
// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
final class LoggerFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Logger
    {
        $config = $this->getConfig($container);
        $loggerName = $config['default_logger'] ?? 'app';

        return $this->createLogger(container: $container, config: $config, loggerName: $loggerName);
    }

    /**
     * @param MonologConfig $config
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function createLogger(ContainerInterface $container, array $config, string $loggerName): Logger
    {
        $loggerConfig = $config['loggers'][$loggerName] ?? ['name' => $loggerName, 'handlers' => []];
        $name = $loggerConfig['name'] ?? $loggerName;

        $logger = new Logger($name);

        $handlers = $loggerConfig['handlers'] ?? [];
        foreach ($handlers as $handlerName) {
            $handlerConfig = $config['handlers'][$handlerName] ?? null;
            if ($handlerConfig === null) {
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
     * @param HandlerConfig $handlerConfig
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function createHandler(ContainerInterface $container, array $handlerConfig): ?HandlerInterface
    {
        $type = $handlerConfig['type'] ?? null;
        if (!is_string($type)) {
            return null;
        }

        $options = $handlerConfig['options'] ?? [];

        return match ($type) {
            'stream' => $this->createStreamHandler($options),
            'doctrine_dbal' => $this->createDoctrineDbalHandler($container, $options),
            default => null,
        };
    }

    /**
     * @param HandlerOptions $options
     */
    private function createStreamHandler(array $options): HandlerInterface
    {
        $stream = $options['stream'] ?? 'data/log/app.log';
        $level = $options['level'] ?? 'debug';
        $bubble = $options['bubble'] ?? true;

        $handler = new StreamHandler($stream, $level, $bubble);
        $formatter = new LineFormatter();
        $handler->setFormatter($formatter);

        return $handler;
    }

    /**
     * @param HandlerOptions $options
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function createDoctrineDbalHandler(ContainerInterface $container, array $options): HandlerInterface
    {
        $connectionService = $options['connection'] ?? 'doctrine.connection.orm_default';
        $table = $options['table'] ?? 'application_log';
        $level = $options['level'] ?? 'error';
        $bubble = $options['bubble'] ?? true;

        $connection = Service::get($container, Connection::class, $connectionService);

        return new DoctrineDbalHandler(connection: $connection, table: $table, level: $level, bubble: $bubble);
    }

    /**
     * @return MonologConfig
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getConfig(ContainerInterface $container): array
    {
        /** @mago-var mixed $config */
        $config = $container->has('config') ? $container->get('config') : [];
        if (!is_array($config)) {
            return [];
        }

        /** @mago-var mixed $monologConfig */
        $monologConfig = $config['monolog'] ?? [];
        if (!is_array($monologConfig)) {
            return [];
        }

        /** @var MonologConfig $monologConfig */
        return $monologConfig;
    }
}
