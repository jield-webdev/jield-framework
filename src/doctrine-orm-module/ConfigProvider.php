<?php

declare(strict_types=1);

namespace DoctrineORMModule;

use Jield\Stdlib\Value;

/**
 * Config provider for DoctrineORMModule config
 */
final class ConfigProvider
{
    /** @return mixed[] */
    public function __invoke(): array
    {
        $config = include __DIR__ . '/../../config/docrine-orm-module/module.config.php';
        Value::setPath(
            $config,
            ['dependencies'],
            Value::accessible($config)['service_manager'],
        );
        $config = Value::array($config);
        unset($config['service_manager']);

        return $config;
    }
}
