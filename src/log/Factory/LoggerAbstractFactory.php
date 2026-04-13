<?php

declare(strict_types=1);

namespace Jield\Log\Factory;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Monolog\Logger;
use Psr\Container\ContainerInterface;

final class LoggerAbstractFactory implements AbstractFactoryInterface
{
    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        if (!str_starts_with($requestedName, Logger::class . '.')) {
            return false;
        }

        $config = $container->has('config') ? $container->get('config') : [];
        if (!is_array($config)) {
            return false;
        }

        $monologConfig = $config['monolog'] ?? [];
        if (!is_array($monologConfig)) {
            return false;
        }

        $loggerName = substr($requestedName, strlen(Logger::class . '.'));
        if ($loggerName === '') {
            return false;
        }

        return isset($monologConfig['loggers'][$loggerName]);
    }

    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): Logger
    {
        $loggerName = substr($requestedName, strlen(Logger::class . '.'));
        $factory = new LoggerFactory();
        $config = $this->getMonologConfig($container);

        return $factory->createLogger(
            container: $container,
            config: $config,
            loggerName: $loggerName
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getMonologConfig(ContainerInterface $container): array
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
