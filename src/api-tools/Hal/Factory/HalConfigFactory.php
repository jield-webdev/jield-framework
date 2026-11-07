<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Psr\Container\ContainerInterface;

use function is_array;

class HalConfigFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): array
    {
        /** @var array<string,mixed> $config */
        $config = $container->has('config') ? $container->get('config') : [];

        return (
            ($config['api-tools-hal'] ?? null) !== null && is_array(value: $config['api-tools-hal'])
                ? $config['api-tools-hal']
                : []
        );
    }
}
