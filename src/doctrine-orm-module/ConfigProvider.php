<?php

declare(strict_types=1);

namespace DoctrineORMModule;

/**
 * Config provider for DoctrineORMModule config
 */
final class ConfigProvider
{
    /** @return mixed[] */
    public function __invoke(): array
    {
        /** @var mixed $config */
        $config = include __DIR__ . '/../../config/docrine-orm-module/module.config.php';
        \Jield\Stdlib\Value::setPath($config, ['dependencies'], \Jield\Stdlib\Value::accessible($config)['service_manager']);
        $config = \Jield\Stdlib\Value::accessible($config);
        unset($config['service_manager']);

        return $config;
    }
}
