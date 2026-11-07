<?php

declare(strict_types=1);

namespace Jield\Log\Factory;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Monolog\Logger;
use Psr\Container\ContainerInterface;

final class LoggerAbstractFactory implements AbstractFactoryInterface
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        if (!str_starts_with($requestedName, Logger::class . '.')) {
            return false;
        }

        $monologConfig = new LoggerFactory()->getConfig($container);

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
        $config = $factory->getConfig($container);

        return $factory->createLogger(container: $container, config: $config, loggerName: $loggerName);
    }
}
